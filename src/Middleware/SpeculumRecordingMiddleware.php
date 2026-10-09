<?php
declare(strict_types=1);

namespace Crustum\Speculum\Middleware;

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Speculum\Middleware\Trait\RecordsRequestEntryTrait;
use Crustum\Speculum\Recording\RequestPathFilter;
use Crustum\Speculum\Speculum;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Starts recording for approved HTTP requests and stores entries.
 *
 * Runs as early as possible so the recorded duration spans the full cycle.
 * The request entry itself is recorded by the late capture middleware (which
 * sees routed params); this middleware records a fallback entry only when
 * the request never reaches it (auth redirect, inner exception).
 */
class SpeculumRecordingMiddleware implements MiddlewareInterface
{
    use RecordsRequestEntryTrait;

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$request instanceof ServerRequest) {
            return $handler->handle($request);
        }

        Speculum::beginRequest();

        $shouldRecord = Configure::read('Speculum.enabled', false)
            && !RequestPathFilter::shouldIgnoreRequest($request);

        if ($shouldRecord) {
            Speculum::startRecording();
            $identity = $request->getAttribute('identity');
            if (is_object($identity)) {
                Speculum::auth($identity);
            }
        }

        $started = Speculum::requestStartedAt() ?? microtime(true);

        try {
            $response = $handler->handle($request);

            if ($shouldRecord && Speculum::isRecording()) {
                $identity = $request->getAttribute('identity');
                if (is_object($identity)) {
                    Speculum::auth($identity);
                }

                $this->recordFallback($request, $response, $started);
            }

            return $response;
        } catch (Throwable $throwable) {
            if ($shouldRecord) {
                try {
                    $this->recordFallback($request, (new Response())->withStatus(500), $started);
                    Speculum::store();
                } catch (Throwable) {
                }
            }

            throw $throwable;
        } finally {
            if ($shouldRecord) {
                Speculum::auth(null);
            }

            Speculum::store();
        }
    }

    /**
     * Record the fallback request entry when the late middleware never ran.
     *
     * @param \Cake\Http\ServerRequest $request Request.
     * @param mixed $response Response or passthrough value.
     * @param float $started Start microtime.
     * @return void
     */
    protected function recordFallback(ServerRequest $request, mixed $response, float $started): void
    {
        if (Speculum::isRequestEntryRecorded() || !$response instanceof Response) {
            return;
        }

        $this->recordRequestEntry($request, $response, $started);
    }
}
