<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Sanitizer\SensitiveData;
use Crustum\Speculum\Speculum;
use Throwable;
use function Cake\Core\pluginSplit;

/**
 * Records completed HTTP requests.
 */
class RequestWatcher extends Watcher
{
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
        if (!Speculum::isRecording() || $this->shouldIgnoreHttpMethod($request) || $this->shouldIgnoreStatusCode($response)) {
            return;
        }

        $params = $this->params($request);
        $matchedRoute = $params['_matchedRoute'] ?? null;
        $duration = (int)floor((microtime(true) - $started) * 1000);
        $slow = $this->isSlowDuration($duration);

        Speculum::recordEntry(EntryType::Request, IncomingEntry::make([
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
            'response' => $this->responsePayload($response),
            'duration' => $duration,
            'slow' => $slow,
            'memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
        ])->tags($this->slowTags($duration)));
    }

    /**
     * Build a PHP-style controller action string (FQCN::action).
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

        return $this->resolveControllerClass($params, $controller) . '::' . $action;
    }

    /**
     * Resolve the controller FQCN from routing params.
     *
     * @param array<string, mixed> $params Routing params.
     * @param string $controller Short controller name.
     * @return string
     */
    protected function resolveControllerClass(array $params, string $controller): string
    {
        $name = $controller;
        $prefix = $params['prefix'] ?? null;
        if (is_string($prefix) && $prefix !== '') {
            $name = str_replace(['\\', '.'], '/', $prefix) . '/' . $name;
        }

        $plugin = $params['plugin'] ?? null;
        $lookup = is_string($plugin) && $plugin !== ''
            ? $plugin . '.' . $name
            : $name;

        $resolved = App::className($lookup, 'Controller', 'Controller');
        if ($resolved !== null) {
            return $resolved;
        }

        return $this->synthesizeControllerClass($lookup);
    }

    /**
     * Build a controller FQCN when the class is not loaded yet.
     *
     * @param string $lookup Plugin-split controller path (e.g. Admin/Users).
     * @return string
     */
    protected function synthesizeControllerClass(string $lookup): string
    {
        [$plugin, $name] = pluginSplit($lookup);
        $relative = 'Controller\\' . str_replace('/', '\\', (string)$name) . 'Controller';

        if ($plugin) {
            if (Plugin::isLoaded($plugin)) {
                $pluginClass = Plugin::getCollection()->get($plugin)::class;
                $base = substr($pluginClass, 0, (int)strrpos($pluginClass, '\\'));
            } else {
                $base = str_replace('/', '\\', $plugin);
            }

            return $base . '\\' . $relative;
        }

        $base = (string)Configure::read('App.namespace', 'App');
        $base = str_replace('/', '\\', rtrim($base, '\\'));

        return $base . '\\' . $relative;
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
