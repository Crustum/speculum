<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Resolver;

use Authorization\AuthorizationServiceInterface;
use Authorization\IdentityInterface;
use Authorization\Policy\Exception\MissingPolicyException;
use Authorization\Policy\ResolverInterface;
use Authorization\Policy\Result;
use Authorization\Policy\ResultInterface;
use Cake\Http\ServerRequest;
use Crustum\Speculum\Resolver\PolicyResolver;
use Crustum\Speculum\Test\TestCase\TestCaseBase;

class PolicyResolverTest extends TestCaseBase
{
    public function testResolvesPolicyFromAuthorizationService(): void
    {
        $policyObject = new Result(true);
        $resolver = $this->createStub(ResolverInterface::class);
        $resolver->method('getPolicy')->willReturn($policyObject);

        $inner = new class ($resolver) implements AuthorizationServiceInterface {
            public ResolverInterface $resolver;

            public function __construct(ResolverInterface $resolver)
            {
                $this->resolver = $resolver;
            }

            public function can(?IdentityInterface $user, string $action, mixed $resource, mixed ...$optionalArgs): bool
            {
                return true;
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

        $request = (new ServerRequest(['url' => '/test']))
            ->withAttribute('authorization', $inner);

        $policyResolver = new PolicyResolver();
        $result = $policyResolver->resolve($request);

        $this->assertSame(Result::class, $result);
    }

    public function testReturnsNullWithoutAuthorizationAttribute(): void
    {
        $request = new ServerRequest(['url' => '/test']);

        $policyResolver = new PolicyResolver();
        $result = $policyResolver->resolve($request);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenResolverThrows(): void
    {
        $resolver = $this->createStub(ResolverInterface::class);
        $resolver->method('getPolicy')->willThrowException(
            new MissingPolicyException(['test']),
        );

        $inner = new class ($resolver) implements AuthorizationServiceInterface {
            public ResolverInterface $resolver;

            public function __construct(ResolverInterface $resolver)
            {
                $this->resolver = $resolver;
            }

            public function can(?IdentityInterface $user, string $action, mixed $resource, mixed ...$optionalArgs): bool
            {
                return true;
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

        $request = (new ServerRequest(['url' => '/test']))
            ->withAttribute('authorization', $inner);

        $policyResolver = new PolicyResolver();
        $result = $policyResolver->resolve($request);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenNoResolverProperty(): void
    {
        $service = $this->createStub(AuthorizationServiceInterface::class);

        $request = (new ServerRequest(['url' => '/test']))
            ->withAttribute('authorization', $service);

        $policyResolver = new PolicyResolver();
        $result = $policyResolver->resolve($request);

        $this->assertNull($result);
    }
}
