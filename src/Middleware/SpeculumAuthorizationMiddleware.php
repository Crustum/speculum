<?php
declare(strict_types=1);

namespace Crustum\Speculum\Middleware;

use Authorization\AuthorizationServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Decorates the Authorization service to dispatch Speculum events on every can/canResult check.
 *
 * Place this middleware after AuthorizationMiddleware so the service attribute is already set.
 */
class SpeculumAuthorizationMiddleware implements MiddlewareInterface
{
    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $service = $request->getAttribute('authorization');
        if ($service instanceof AuthorizationServiceInterface && !($service instanceof SpeculumAuthorizationServiceDecorator)) {
            $request = $request->withAttribute('authorization', new SpeculumAuthorizationServiceDecorator($service, $request));
        }

        return $handler->handle($request);
    }
}
