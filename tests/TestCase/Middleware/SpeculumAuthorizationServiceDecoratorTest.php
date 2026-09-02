<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Middleware;

use Authorization\AuthorizationServiceInterface;
use Authorization\IdentityInterface;
use Authorization\Policy\ResolverInterface;
use Authorization\Policy\Result;
use Authorization\Policy\ResultInterface;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Http\ServerRequest;
use Crustum\Speculum\Middleware\SpeculumAuthorizationServiceDecorator;
use Crustum\Speculum\Test\TestCase\TestCaseBase;

class SpeculumAuthorizationServiceDecoratorTest extends TestCaseBase
{
    private function createInner(): AuthorizationServiceInterface
    {
        $resolver = $this->createStub(ResolverInterface::class);

        return new class ($resolver) implements AuthorizationServiceInterface {
            public function can(?IdentityInterface $user, string $action, mixed $resource, mixed ...$optionalArgs): bool
            {
                return $this->canResult($user, $action, $resource, ...$optionalArgs)->getStatus();
            }

            public function canResult(?IdentityInterface $user, string $action, mixed $resource, mixed ...$optionalArgs): ResultInterface
            {
                return new Result(true);
            }

            public function applyScope(?IdentityInterface $user, string $action, mixed $resource, mixed ...$optionalArgs): mixed
            {
                return $resource;
            }

            public function authorizationChecked(): bool
            {
                return true;
            }

            public function skipAuthorization()
            {
                return $this;
            }
        };
    }

    public function testDispatchesSpeculumAuthorizationCheckedEvent(): void
    {
        $inner = $this->createInner();
        $request = new ServerRequest(['url' => '/test']);
        $decorator = new SpeculumAuthorizationServiceDecorator($inner, $request);

        $dispatched = [];
        EventManager::instance()->on('Speculum.Authorization.checked', function (Event $event) use (&$dispatched): void {
            $dispatched[] = $event;
        });

        $result = $decorator->can(null, 'read', 'Articles');

        $this->assertTrue($result);
        $this->assertCount(1, $dispatched);
        $this->assertSame('Speculum.Authorization.checked', $dispatched[0]->getName());
        $this->assertTrue($dispatched[0]->getData('allowed'));
        $this->assertSame('read', $dispatched[0]->getData('action'));
        $this->assertSame('Articles', $dispatched[0]->getData('resource'));
        $this->assertSame($request, $dispatched[0]->getData('request'));

        EventManager::instance()->off('Speculum.Authorization.checked');
    }

    public function testDispatchesEventOnCanResult(): void
    {
        $inner = $this->createInner();
        $request = new ServerRequest(['url' => '/test']);
        $decorator = new SpeculumAuthorizationServiceDecorator($inner, $request);

        $dispatched = [];
        EventManager::instance()->on('Speculum.Authorization.checked', function (Event $event) use (&$dispatched): void {
            $dispatched[] = $event;
        });

        $result = $decorator->canResult(null, 'delete', 'Articles');

        $this->assertInstanceOf(ResultInterface::class, $result);
        $this->assertTrue($result->getStatus());
        $this->assertCount(1, $dispatched);
        $this->assertSame('delete', $dispatched[0]->getData('action'));

        EventManager::instance()->off('Speculum.Authorization.checked');
    }

    public function testPassesFalseAllowedOnDeniedResult(): void
    {
        $inner = new class () implements AuthorizationServiceInterface {
            public function can(?IdentityInterface $user, string $action, mixed $resource, mixed ...$optionalArgs): bool
            {
                return false;
            }

            public function canResult(?IdentityInterface $user, string $action, mixed $resource, mixed ...$optionalArgs): ResultInterface
            {
                return new Result(false);
            }

            public function applyScope(?IdentityInterface $user, string $action, mixed $resource, mixed ...$optionalArgs): mixed
            {
                return $resource;
            }

            public function authorizationChecked(): bool
            {
                return true;
            }

            public function skipAuthorization()
            {
                return $this;
            }
        };

        $request = new ServerRequest(['url' => '/test']);
        $decorator = new SpeculumAuthorizationServiceDecorator($inner, $request);

        $dispatched = [];
        EventManager::instance()->on('Speculum.Authorization.checked', function (Event $event) use (&$dispatched): void {
            $dispatched[] = $event;
        });

        $decorator->can(null, 'write', 'Articles');

        $this->assertCount(1, $dispatched);
        $this->assertFalse($dispatched[0]->getData('allowed'));

        EventManager::instance()->off('Speculum.Authorization.checked');
    }

    public function testApplyScopeDoesNotDispatchEvent(): void
    {
        $inner = $this->createInner();
        $request = new ServerRequest(['url' => '/test']);
        $decorator = new SpeculumAuthorizationServiceDecorator($inner, $request);

        $dispatched = [];
        EventManager::instance()->on('Speculum.Authorization.checked', function (Event $event) use (&$dispatched): void {
            $dispatched[] = $event;
        });

        $decorator->applyScope(null, 'read', 'Articles');

        $this->assertCount(0, $dispatched);

        EventManager::instance()->off('Speculum.Authorization.checked');
    }

    public function testForwardsAuthorizationChecked(): void
    {
        $inner = $this->createInner();
        $request = new ServerRequest(['url' => '/test']);
        $decorator = new SpeculumAuthorizationServiceDecorator($inner, $request);

        $this->assertTrue($decorator->authorizationChecked());
    }

    public function testForwardsSkipAuthorization(): void
    {
        $inner = $this->createInner();
        $request = new ServerRequest(['url' => '/test']);
        $decorator = new SpeculumAuthorizationServiceDecorator($inner, $request);

        $result = $decorator->skipAuthorization();
        $this->assertSame($decorator, $result);
    }
}
