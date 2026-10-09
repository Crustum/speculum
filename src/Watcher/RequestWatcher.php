<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Utility\Inflector;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Resolver\PolicyResolver;
use Crustum\Speculum\Sanitizer\SensitiveData;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Watcher\Trait\RouteIgnoreTrait;
use Throwable;

/**
 * Records completed HTTP requests.
 */
class RequestWatcher extends Watcher
{
    use RouteIgnoreTrait;

    /**
     * @inheritDoc
     */
    public function register(): void
    {
    }

    /**
     * Record a completed HTTP request entry.
     *
     * @param \Cake\Http\ServerRequest $request Request.
     * @param \Cake\Http\Response $response Response.
     * @param float $started Start microtime.
     * @return void
     */
    public function record(ServerRequest $request, Response $response, float $started): void
    {
        if (
            !Speculum::isRecording()
            || $this->shouldIgnore($request)
            || $this->shouldIgnoreHttpMethod($request)
            || $this->shouldIgnoreStatusCode($response)
        ) {
            return;
        }

        $isStreamable = !$response->getBody()->isSeekable();
        $skipBody = $this->shouldIgnoreContentType($response)
            || ($isStreamable && $this->ignoresStreamable());

        if (!$skipBody) {
            $responseBody = $this->responsePayload($response);
        } elseif ($isStreamable && !$this->shouldIgnoreContentType($response)) {
            $responseBody = 'Streaming Response';
        } else {
            $responseBody = 'Skipped By Speculum';
        }

        $params = $this->params($request);
        $matchedRoute = $params['_matchedRoute'] ?? null;
        $duration = (int)floor((microtime(true) - $started) * 1000);
        $slow = $this->isSlowDuration($duration);

        Speculum::recordEntry(EntryType::Request, IncomingEntry::make(array_filter([
            'ip_address' => $request->clientIp(),
            'uri' => $request->getRequestTarget(),
            'method' => $request->getMethod(),
            'controller_action' => $this->controllerAction($params),
            'matched_route' => is_string($matchedRoute) ? $matchedRoute : null,
            'params' => $params,
            'headers' => $this->headers($request->getHeaders()),
            'payload' => $this->payload($request->getData()),
            'query' => $this->payload($request->getQueryParams()),
            'session' => $this->session($request),
            'response_headers' => $this->headers($response->getHeaders()),
            'response_status' => $response->getStatusCode(),
            'response' => $responseBody,
            'duration' => $duration,
            'slow' => $slow,
            'memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
            'policy' => (new PolicyResolver())->resolve($request),
            'is_streamable' => $isStreamable ? true : null,
        ]))->tags($this->slowTags($duration)));
    }

    /**
     * Build the Cake route handler string (`prefix:Plugin.Controller:action`).
     *
     * Same shape `bin/cake routes` prints: single colon, no `Controller`
     * suffix, dashed segments camelised.
     *
     * @param array<string, mixed> $params Routing params.
     * @return string|null
     */
    protected function controllerAction(array $params): ?string
    {
        $controller = $params['controller'] ?? null;
        $action = $params['action'] ?? null;
        if (!is_string($controller) || $controller === '' || !is_string($action) || $action === '') {
            return null;
        }

        $parts = [];
        $prefix = $params['prefix'] ?? null;
        if (is_string($prefix) && $prefix !== '') {
            $parts[] = $prefix . ':';
        }

        $plugin = $params['plugin'] ?? null;
        if (is_string($plugin) && $plugin !== '') {
            $parts[] = $plugin . '.';
        }

        $parts[] = Inflector::camelize(str_replace('-', '_', $controller)) . ':' . $action;

        return implode('', $parts);
    }

    /**
     * Extract serializable routing params from the request.
     *
     * @param \Cake\Http\ServerRequest $request Request.
     * @return array<string, mixed>
     */
    protected function params(ServerRequest $request): array
    {
        $params = $request->getAttribute('params', []);
        if (!is_array($params)) {
            return [];
        }

        return SensitiveData::parameters(
            $this->normalizeForStorage($params),
        );
    }

    /**
     * Extract serializable session data from the request.
     *
     * @param \Cake\Http\ServerRequest $request Request.
     * @return array<string, mixed>
     */
    protected function session(ServerRequest $request): array
    {
        try {
            $data = $request->getSession()->read();
        } catch (Throwable) {
            return [];
        }

        if (!is_array($data) || $data === []) {
            return [];
        }

        return SensitiveData::parameters(
            $this->normalizeForStorage($data),
        );
    }

    /**
     * Drop non-JSON-serializable values from a nested array.
     *
     * @param array<string, mixed> $data Data.
     * @return array<string, mixed>
     */
    protected function normalizeForStorage(array $data): array
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = $this->normalizeForStorage($value);
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Determine whether the request HTTP method should be ignored.
     *
     * @param \Cake\Http\ServerRequest $request Request.
     * @return bool
     */
    protected function shouldIgnoreHttpMethod(ServerRequest $request): bool
    {
        $ignored = array_map(strtolower(...), $this->options['ignore_http_methods'] ?? []);

        return in_array(strtolower($request->getMethod()), $ignored, true);
    }

    /**
     * Determine whether the response status code should be ignored.
     *
     * @param \Cake\Http\Response $response Response.
     * @return bool
     */
    protected function shouldIgnoreStatusCode(Response $response): bool
    {
        return in_array($response->getStatusCode(), $this->options['ignore_status_codes'] ?? [], true);
    }

    /**
     * Determine whether the response content type should be ignored.
     *
     * @param \Cake\Http\Response $response Response.
     * @return bool
     */
    public function shouldIgnoreContentType(Response $response): bool
    {
        $ignored = $this->options['ignore_content_types'] ?? [];
        if ($ignored === []) {
            return false;
        }

        $contentType = $response->getHeaderLine('Content-Type');
        $mediaType = strtolower(trim(explode(';', $contentType)[0]));
        foreach ($ignored as $pattern) {
            if (fnmatch($pattern, $contentType) || fnmatch($pattern, $mediaType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether non-seekable (streaming) response bodies skip recording.
     *
     * @return bool
     */
    public function ignoresStreamable(): bool
    {
        return (bool)($this->options['ignore_streamable'] ?? true);
    }

    /**
     * Format and redact HTTP headers for storage.
     *
     * @param array<string, array<string>> $headers Headers.
     * @return array<string, string>
     */
    protected function headers(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $key => $values) {
            $formatted[strtolower((string)$key)] = implode(', ', $values);
        }

        return SensitiveData::headers($formatted);
    }

    /**
     * Format and redact request payload data for storage.
     *
     * @param array<string, mixed>|string $payload Payload.
     * @return array<string, mixed>|string
     */
    protected function payload(array|string $payload): array|string
    {
        if (is_string($payload)) {
            $sanitized = SensitiveData::payload($payload);

            return is_string($sanitized) ? $sanitized : $payload;
        }

        return SensitiveData::parameters($payload);
    }

    /**
     * Extract a response body payload suitable for Speculum storage.
     *
     * @param \Cake\Http\Response $response Response.
     * @return mixed
     */
    protected function responsePayload(Response $response): mixed
    {
        $sizeLimit = (int)($this->options['size_limit'] ?? 64) * 1024;
        $body = $this->readResponseBody($response);
        if (strlen($body) > $sizeLimit) {
            return 'Purged By Speculum';
        }

        $contentType = $response->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($body, true);

            return is_array($decoded)
                ? SensitiveData::responseParameters($decoded)
                : $body;
        }

        return $body;
    }

    /**
     * Read response body without leaving the stream at EOF (empty page to the client).
     *
     * @param \Cake\Http\Response $response Response.
     * @return string
     */
    protected function readResponseBody(Response $response): string
    {
        $stream = $response->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $body = $stream->getContents();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $body;
    }
}
