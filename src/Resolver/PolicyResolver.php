<?php
declare(strict_types=1);

namespace Crustum\Speculum\Resolver;

use Authorization\AuthorizationServiceInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionProperty;
use Throwable;

/**
 * Resolves the authorization policy class for a request.
 */
class PolicyResolver
{
    /**
     * Resolve the policy class name for the current request.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request.
     * @return string|null Policy class name or null.
     */
    public function resolve(ServerRequestInterface $request): ?string
    {
        try {
            $service = $request->getAttribute('authorization');
            if (!$service instanceof AuthorizationServiceInterface) {
                return null;
            }

            $inner = $service;
            if (property_exists($service, 'inner')) {
                $inner = $service->inner;
            }

            $reflection = new ReflectionProperty($inner, 'resolver');
            $resolver = $reflection->getValue($inner);
            if (!is_object($resolver) || !method_exists($resolver, 'getPolicy')) {
                return null;
            }

            $policy = $resolver->getPolicy($request);

            return is_object($policy) ? $policy::class : null;
        } catch (Throwable) {
            return null;
        }
    }
}
