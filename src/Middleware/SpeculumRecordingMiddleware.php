<?php
declare(strict_types=1);

namespace Crustum\Speculum\Middleware;

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Speculum\Recording\RequestPathFilter;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Watcher\RequestWatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Starts recording for approved HTTP requests and records request entries.
 */
class SpeculumRecordingMiddleware implements MiddlewareInterface
{
    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$request instanceof ServerRequest) {
            return $handler->handle($request);
        }

        $shouldRecord = Configure::read('Speculum.enabled', false)
            && !RequestPathFilter::shouldIgnoreRequest($request);

        if ($shouldRecord) {
            Speculum::startRecording();
            $identity = $request->getAttribute('identity');
            if (is_object($identity)) {
                Speculum::auth($identity);
            }
        }

        $started = microtime(true);

        try {
            $response = $handler->handle($request);

            if (
                $shouldRecord
                && Speculum::isRecording()
                && WatcherRegistry::has(RequestWatcher::class)
                && $response instanceof Response
            ) {
                $identity = $request->getAttribute('identity');
                if (is_object($identity)) {
                    Speculum::auth($identity);
                }

                $options = Configure::read('Speculum.watchers.' . RequestWatcher::class, []);
                $watcher = new RequestWatcher(is_array($options) ? $options : []);
                $stream = $response->getBody();
                if (!$stream->isSeekable()) {
                    $contents = $stream->getContents();
                    $response = $response->withStringBody($contents);
                }

                $watcher->record($request, $response, $started);
                $body = $response->getBody();
                if ($body->isSeekable()) {
                    $body->rewind();
                }
            }

            return $response;
        } catch (Throwable $throwable) {
            if ($shouldRecord) {
                try {
                    Speculum::store();
                } catch (Throwable) {
                }
            }

            throw $throwable;
        } finally {
            if ($shouldRecord) {
                Speculum::auth(null);
            }
        }
    }
}
