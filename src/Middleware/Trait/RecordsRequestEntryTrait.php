<?php
declare(strict_types=1);

namespace Crustum\Speculum\Middleware\Trait;

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Watcher\RequestWatcher;

/**
 * Shared request entry recording for the recording middlewares.
 *
 * Both the early recording middleware (fallback for short-circuited requests)
 * and the late capture middleware (routed requests) record through here, so
 * the watcher construction and stream handling stay identical.
 */
trait RecordsRequestEntryTrait
{
    /**
     * Build the request watcher when enabled.
     *
     * @return \Crustum\Speculum\Watcher\RequestWatcher|null
     */
    protected function buildRequestWatcher(): ?RequestWatcher
    {
        if (!WatcherRegistry::has(RequestWatcher::class)) {
            return null;
        }

        $options = Configure::read('Speculum.watchers.' . RequestWatcher::class, []);

        return new RequestWatcher(is_array($options) ? $options : []);
    }

    /**
     * Record the request entry and mark the cycle as captured.
     *
     * @param \Cake\Http\ServerRequest $request Request.
     * @param \Cake\Http\Response $response Response.
     * @param float $started Start microtime.
     * @return void
     */
    protected function recordRequestEntry(ServerRequest $request, Response $response, float $started): void
    {
        $watcher = $this->buildRequestWatcher();
        if ($watcher === null) {
            return;
        }

        $response = $this->bufferResponseBody($response, $watcher);
        $watcher->record($request, $response, $started);
        $this->rewindResponseBody($response);
        Speculum::markRequestEntryRecorded();
    }

    /**
     * Buffer a non-seekable response body so the watcher can read it.
     *
     * @param \Cake\Http\Response $response Response.
     * @param \Crustum\Speculum\Watcher\RequestWatcher $watcher Watcher.
     * @return \Cake\Http\Response
     */
    protected function bufferResponseBody(Response $response, RequestWatcher $watcher): Response
    {
        if ($watcher->shouldIgnoreContentType($response)) {
            return $response;
        }

        if ($watcher->ignoresStreamable() && !$response->getBody()->isSeekable()) {
            return $response;
        }

        $stream = $response->getBody();
        if ($stream->isSeekable()) {
            return $response;
        }

        return $response->withStringBody($stream->getContents());
    }

    /**
     * Rewind a seekable response body after recording.
     *
     * @param \Cake\Http\Response $response Response.
     * @return void
     */
    protected function rewindResponseBody(Response $response): void
    {
        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }
    }
}
