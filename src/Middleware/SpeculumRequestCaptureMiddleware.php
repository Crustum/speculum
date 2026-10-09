<?php
declare(strict_types=1);

namespace Crustum\Speculum\Middleware;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Speculum\Middleware\Trait\RecordsRequestEntryTrait;
use Crustum\Speculum\Speculum;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Records the routed request entry late in the pipeline.
 *
 * Runs after the routing middleware, so the recorded entry carries the
 * resolved controller, action, plugin, and prefix. Requests short-circuited
 * before this middleware (auth redirect, inner exception) are covered by the
 * early recording middleware fallback instead.
 */
class SpeculumRequestCaptureMiddleware implements MiddlewareInterface
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

        $started = Speculum::requestStartedAt() ?? microtime(true);
        $response = $handler->handle($request);

        if (
            Speculum::isRecording()
            && !Speculum::isRequestEntryRecorded()
            && $response instanceof Response
        ) {
            $this->recordRequestEntry($request, $response, $started);
        }

        return $response;
    }
}
