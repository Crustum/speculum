<?php
declare(strict_types=1);

namespace Crustum\Speculum\Middleware;

use Authorization\AuthorizationServiceInterface;
use Authorization\IdentityInterface;
use Authorization\Policy\ResultInterface;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Crustum\Speculum\Resolver\PolicyResolver;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Decorates the Authorization service to dispatch Speculum events on every can/canResult check.
 */
class SpeculumAuthorizationServiceDecorator implements AuthorizationServiceInterface
{
    /**
     * @var \Authorization\AuthorizationServiceInterface
     */
    protected AuthorizationServiceInterface $inner;

    /**
     * @var \Psr\Http\Message\ServerRequestInterface
     */
    protected ServerRequestInterface $request;

    /**
     * @param \Authorization\AuthorizationServiceInterface $inner The decorated service.
     * @param \Psr\Http\Message\ServerRequestInterface $request The current request.
     */
    public function __construct(AuthorizationServiceInterface $inner, ServerRequestInterface $request)
    {
        $this->inner = $inner;
        $this->request = $request;
    }

    /**
     * @inheritDoc
     */
    public function can(?IdentityInterface $user, string $action, mixed $resource, mixed ...$optionalArgs): bool
    {
        $result = $this->inner->canResult($user, $action, $resource, ...$optionalArgs);

        $this->dispatchEvent($user, $action, $resource, $result);

        return $result->getStatus();
    }

    /**
     * @inheritDoc
     */
    public function canResult(?IdentityInterface $user, string $action, mixed $resource, mixed ...$optionalArgs): ResultInterface
    {
        $result = $this->inner->canResult($user, $action, $resource, ...$optionalArgs);

        $this->dispatchEvent($user, $action, $resource, $result);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function applyScope(?IdentityInterface $user, string $action, mixed $resource, mixed ...$optionalArgs): mixed
    {
        return $this->inner->applyScope($user, $action, $resource, ...$optionalArgs);
    }

    /**
     * @inheritDoc
     */
    public function authorizationChecked(): bool
    {
        return $this->inner->authorizationChecked();
    }

    /**
     * @inheritDoc
     */
    public function skipAuthorization()
    {
        $this->inner->skipAuthorization();

        return $this;
    }

    /**
     * Dispatch the Speculum authorization event.
     */
    protected function dispatchEvent(
        ?IdentityInterface $user,
        string $action,
        mixed $resource,
        ResultInterface $result,
    ): void {
        $policyClass = (new PolicyResolver())->resolve($this->request);

        $event = new Event('Speculum.Authorization.checked', null, [
            'user' => $user,
            'action' => $action,
            'resource' => $resource,
            'result' => $result,
            'allowed' => $result->getStatus(),
            'policy_class' => $policyClass,
            'request' => $this->request,
        ]);
        EventManager::instance()->dispatch($event);
    }
}
