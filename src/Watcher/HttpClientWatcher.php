<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\Http\Client\ClientEvent;
use Cake\Http\Client\Response;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Sanitizer\SensitiveData;
use Crustum\Speculum\Speculum;
use Psr\Http\Message\RequestInterface;
use SplObjectStorage;

/**
 * Records outbound CakePHP Http Client requests (`HttpClient.afterSend`).
 *
 * Streamed responses complete after `afterSend` fires (the body must stay
 * untouched for the live stream). The Ai plugin's tee stream dispatches
 * `HttpClient.afterSendStream` with the captured body once the stream reaches
 * EOF; this watcher patches the recorded entry in place via `EntryUpdate`.
 */
class HttpClientWatcher extends Watcher
{
    /**
     * Request start times for duration calculation.
     *
     * @var \SplObjectStorage<\Psr\Http\Message\RequestInterface, float>
     */
    protected SplObjectStorage $startTimes;

    /**
     * Recorded entry UUIDs awaiting a streamed response body, keyed by request.
     *
     * @var \SplObjectStorage<\Psr\Http\Message\RequestInterface, string>
     */
    protected SplObjectStorage $streamContexts;

    /**
     * Create an HTTP client watcher.
     *
     * @param array<string, mixed> $options Watcher options.
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        $this->startTimes = new SplObjectStorage();
        $this->streamContexts = new SplObjectStorage();
    }

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        EventManager::instance()->on('HttpClient.beforeSend', function (EventInterface $event): void {
            $this->beforeSend($event);
        });

        EventManager::instance()->on('HttpClient.afterSend', function (EventInterface $event): void {
            $this->afterSend($event);
        });

        EventManager::instance()->on('HttpClient.afterSendStream', function (EventInterface $event): void {
            $this->afterSendStream($event);
        });

        foreach (['HttpClient.afterRequest', 'Http.Client.afterRequest'] as $eventName) {
            EventManager::instance()->on($eventName, function (EventInterface $event): void {
                $this->record(
                    (string)($event->getData('method') ?? 'GET'),
                    (string)($event->getData('url') ?? ''),
                    (array)($event->getData('headers') ?? []),
                    $event->getData('request_body') ?? $event->getData('data'),
                    $event->getData('response'),
                );
            });
        }
    }

    /**
     * Capture the outbound request start time before send.
     *
     * @param \Cake\Event\EventInterface<object> $event HttpClient.beforeSend event.
     * @return void
     */
    public function beforeSend(EventInterface $event): void
    {
        $request = $this->resolveRequest($event);
        if ($request instanceof RequestInterface) {
            $this->startTimes[$request] = microtime(true);
        }
    }

    /**
     * Record an outbound HTTP client request after send.
     *
     * @param \Cake\Event\EventInterface<object> $event HttpClient.afterSend event.
     * @return void
     */
    public function afterSend(EventInterface $event): void
    {
        $request = $this->resolveRequest($event);
        if (!$request instanceof RequestInterface) {
            return;
        }

        $response = $this->resolveResponse($event);
        $duration = null;
        $hasStart = $this->startTimes->offsetExists($request);
        if ($hasStart) {
            $duration = (int)floor((microtime(true) - $this->startTimes[$request]) * 1000);
        }

        $uuid = $this->recordFromHttp($request, $response, $duration);

        if ($uuid !== null && $event->getData('is_streaming') === true) {
            $this->streamContexts[$request] = $uuid;
        } elseif ($hasStart) {
            $this->startTimes->offsetUnset($request);
        }
    }

    /**
     * Patch a recorded entry with the full body captured at stream EOF.
     *
     * @param \Cake\Event\EventInterface<object> $event HttpClient.afterSendStream event.
     * @return void
     */
    public function afterSendStream(EventInterface $event): void
    {
        $request = $this->resolveRequest($event);
        if (!$request instanceof RequestInterface || !$this->streamContexts->offsetExists($request)) {
            return;
        }

        $uuid = $this->streamContexts[$request];
        $this->streamContexts->offsetUnset($request);

        $response = $this->resolveResponse($event);
        if (!$response instanceof Response) {
            return;
        }

        $duration = null;
        if ($this->startTimes->offsetExists($request)) {
            $duration = (int)floor((microtime(true) - $this->startTimes[$request]) * 1000);
            $this->startTimes->offsetUnset($request);
        }

        Speculum::recordUpdate($this->makeDurationUpdate(
            $uuid,
            EntryType::HttpClient,
            ['response' => SensitiveData::payload($this->formatResponseBody($response))],
            $duration,
        ));
    }

    /**
     * Record an outbound HTTP request/response pair.
     *
     * @param \Psr\Http\Message\RequestInterface $request Outbound request.
     * @param \Cake\Http\Client\Response|null $response Response when available.
     * @param int|null $duration Duration in milliseconds.
     * @return string|null Recorded entry UUID, or null when nothing was recorded.
     */
    public function recordFromHttp(
        RequestInterface $request,
        ?Response $response = null,
        ?int $duration = null,
    ): ?string {
        $uri = (string)$request->getUri();
        if (!Speculum::isRecording() || $uri === '' || $this->shouldIgnoreHost($uri)) {
            return null;
        }

        $payload = [
            'method' => strtoupper($request->getMethod()),
            'uri' => $uri,
            'headers' => SensitiveData::headers($this->flattenHeaders($request->getHeaders())),
            'payload' => SensitiveData::payload($this->extractRequestPayload($request)),
            'response_status' => $response?->getStatusCode(),
            'response_headers' => $response instanceof Response
                ? SensitiveData::headers($this->flattenHeaders($response->getHeaders()))
                : [],
            'response' => SensitiveData::payload($this->formatResponseBody($response)),
            'duration' => $duration,
        ];

        $host = parse_url($uri, PHP_URL_HOST);
        $entry = IncomingEntry::make($payload);
        if (is_string($host) && $host !== '') {
            $entry->tags([$host]);
        }

        Speculum::recordEntry(EntryType::HttpClient, $entry);

        return $entry->uuid;
    }

    /**
     * Record an outbound HTTP client request from discrete fields.
     *
     * @param string $method HTTP method.
     * @param string $uri Request URI.
     * @param array<string, mixed> $headers Headers.
     * @param mixed $payload Request payload.
     * @param mixed $response Response.
     * @param int|null $duration Duration in milliseconds.
     * @return void
     */
    public function record(
        string $method,
        string $uri,
        array $headers = [],
        mixed $payload = null,
        mixed $response = null,
        ?int $duration = null,
    ): void {
        if (!Speculum::isRecording() || $uri === '' || $this->shouldIgnoreHost($uri)) {
            return;
        }

        $status = null;
        $responseHeaders = [];
        $responseBody = null;
        if ($response instanceof Response) {
            $status = $response->getStatusCode();
            $responseHeaders = $this->flattenHeaders($response->getHeaders());
            $responseBody = $this->formatResponseBody($response);
        } elseif (is_array($response)) {
            $status = $response['status'] ?? $response['code'] ?? null;
            $responseHeaders = $response['headers'] ?? [];
            $headerMap = is_array($responseHeaders) ? $responseHeaders : [];
            $contentType = (string)($headerMap['content-type'] ?? $headerMap['Content-Type'] ?? '');
            $rawBody = $response['body'] ?? null;
            $responseBody = is_string($rawBody)
                ? $this->formatRawBody($rawBody, $contentType)
                : $rawBody;
        }

        $host = parse_url($uri, PHP_URL_HOST);
        $entry = IncomingEntry::make([
            'method' => strtoupper($method),
            'uri' => $uri,
            'headers' => SensitiveData::headers($headers),
            'payload' => SensitiveData::payload($payload),
            'response_status' => $status,
            'response_headers' => SensitiveData::headers(
                is_array($responseHeaders) ? $responseHeaders : [],
            ),
            'response' => SensitiveData::payload($responseBody),
            'duration' => $duration,
        ]);
        if (is_string($host) && $host !== '') {
            $entry->tags([$host]);
        }

        Speculum::recordEntry(EntryType::HttpClient, $entry);
    }

    /**
     * Resolve the PSR request from an HttpClient event.
     *
     * @param \Cake\Event\EventInterface<object> $event Event.
     * @return \Psr\Http\Message\RequestInterface|null
     */
    protected function resolveRequest(EventInterface $event): ?RequestInterface
    {
        if ($event instanceof ClientEvent) {
            return $event->getRequest();
        }

        $request = $event->getData('request');

        return $request instanceof RequestInterface ? $request : null;
    }

    /**
     * Cake ClientEvent moves `response` into the event result.
     *
     * @param \Cake\Event\EventInterface<object> $event Event.
     * @return \Cake\Http\Client\Response|null
     */
    protected function resolveResponse(EventInterface $event): ?Response
    {
        if ($event instanceof ClientEvent) {
            return $event->getResult();
        }

        $response = $event->getData('response');
        if ($response instanceof Response) {
            return $response;
        }

        $result = $event->getResult();

        return $result instanceof Response ? $result : null;
    }

    /**
     * Extract a serializable payload from the request body.
     *
     * @param \Psr\Http\Message\RequestInterface $request Request.
     * @return mixed
     */
    protected function extractRequestPayload(RequestInterface $request): mixed
    {
        $body = $request->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        $contents = (string)$body;
        if ($body->isSeekable()) {
            $body->rewind();
        }

        if ($contents === '') {
            return null;
        }

        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        if ($this->isBinaryContent($contents, $contentType)) {
            return sprintf('Binary Request (%d bytes)', strlen($contents));
        }

        $decoded = json_decode($contents, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $contents;
    }

    /**
     * Format a client response body for Speculum storage.
     *
     * @param \Cake\Http\Client\Response|null $response Response.
     * @return mixed
     */
    protected function formatResponseBody(?Response $response): mixed
    {
        if (!$response instanceof Response) {
            return null;
        }

        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        $contents = (string)$body;
        if ($body->isSeekable()) {
            $body->rewind();
        }

        if ($contents === '') {
            return 'Empty Response';
        }

        return $this->formatRawBody($contents, strtolower($response->getHeaderLine('Content-Type')), $response);
    }

    /**
     * Normalize a raw HTTP body for JSON-safe Speculum storage.
     *
     * @param string $contents Raw body.
     * @param string $contentType Content-Type header value.
     * @param \Cake\Http\Client\Response|null $response Optional response for redirect/html hints.
     * @return mixed
     */
    protected function formatRawBody(
        string $contents,
        string $contentType = '',
        ?Response $response = null,
    ): mixed {
        if ($contents === '') {
            return 'Empty Response';
        }

        if (!$this->contentWithinLimits($contents)) {
            return 'Purged By Speculum';
        }

        $contentType = strtolower($contentType);
        if ($this->isBinaryContent($contents, $contentType)) {
            return sprintf('Binary Response (%d bytes)', strlen($contents));
        }

        $decoded = json_decode($contents, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        if (str_starts_with($contentType, 'text/plain') || str_starts_with($contentType, 'application/json')) {
            return $contents;
        }

        if ($response instanceof Response && $response->isRedirect()) {
            return 'Redirected to ' . $response->getHeaderLine('Location');
        }

        if (str_contains($contentType, 'html')) {
            return 'HTML Response';
        }

        return $contents;
    }

    /**
     * Whether a body should not be stored as a UTF-8 JSON string.
     *
     * @param string $contents Raw body.
     * @param string $contentType Content-Type header value.
     * @return bool
     */
    protected function isBinaryContent(string $contents, string $contentType = ''): bool
    {
        $contentType = strtolower($contentType);
        $binaryTypes = [
            'application/zip',
            'application/x-zip',
            'application/x-zip-compressed',
            'application/octet-stream',
            'application/gzip',
            'application/x-gzip',
            'application/pdf',
            'application/wasm',
            'image/',
            'audio/',
            'video/',
            'multipart/',
            'font/',
        ];
        foreach ($binaryTypes as $type) {
            if (str_contains($contentType, $type)) {
                return true;
            }
        }

        if (str_starts_with($contents, "PK\x03\x04") || str_starts_with($contents, "PK\x05\x06")) {
            return true;
        }

        if (str_contains($contents, "\0")) {
            return true;
        }

        return !mb_check_encoding($contents, 'UTF-8');
    }

    /**
     * Flatten PSR header maps into string values.
     *
     * @param array<string, array<int, string>> $headers PSR headers.
     * @return array<string, string>
     */
    protected function flattenHeaders(array $headers): array
    {
        $flat = [];
        foreach ($headers as $name => $values) {
            $flat[strtolower((string)$name)] = implode(', ', $values);
        }

        return $flat;
    }

    /**
     * Determine whether response content is within the configured size limit.
     *
     * @param string $content Response body.
     * @return bool
     */
    protected function contentWithinLimits(string $content): bool
    {
        $limit = (int)($this->options['size_limit'] ?? 64);

        return strlen($content) / 1000 <= $limit;
    }

    /**
     * Determine whether the request host should be ignored.
     *
     * @param string $uri URI.
     * @return bool
     */
    protected function shouldIgnoreHost(string $uri): bool
    {
        $host = parse_url($uri, PHP_URL_HOST) ?: '';
        foreach ($this->options['ignore_hosts'] ?? [] as $pattern) {
            if (fnmatch((string)$pattern, (string)$host)) {
                return true;
            }
        }

        return false;
    }
}
