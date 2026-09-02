<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Middleware;

use Authorization\AuthorizationServiceInterface;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Speculum\Middleware\SpeculumAuthorizationMiddleware;
use Crustum\Speculum\Middleware\SpeculumAuthorizationServiceDecorator;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SpeculumAuthorizationMiddlewareTest extends TestCaseBase
{
    public function testDecoratesAuthorizationServiceOnRequest(): void
    {
        $inner = $this->createStub(AuthorizationServiceInterface::class);
        $request = (new ServerRequest(['url' => '/test']))
            ->withAttribute('authorization', $inner);

        $handler = new class () implements RequestHandlerInterface {
            public ServerRequestInterface $receivedRequest;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->receivedRequest = $request;

                return new Response();
            }
        };

        $middleware = new SpeculumAuthorizationMiddleware();
        $middleware->process($request, $handler);

        $this->assertInstanceOf(SpeculumAuthorizationServiceDecorator::class, $handler->receivedRequest->getAttribute('authorization'));
    }

    public function testDoesNotDoubleWrapDecorator(): void
    {
        $inner = $this->createStub(AuthorizationServiceInterface::class);
        $originalDecorator = new SpeculumAuthorizationServiceDecorator($inner, new ServerRequest(['url' => '/test']));
        $request = (new ServerRequest(['url' => '/test']))
            ->withAttribute('authorization', $originalDecorator);

        $handler = new class () implements RequestHandlerInterface {
            public ServerRequestInterface $receivedRequest;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->receivedRequest = $request;

                return new Response();
            }
        };

        $middleware = new SpeculumAuthorizationMiddleware();
        $middleware->process($request, $handler);

        $this->assertSame($originalDecorator, $handler->receivedRequest->getAttribute('authorization'));
    }

    public function testPassesThroughWhenNoAuthorizationAttribute(): void
    {
        $request = new ServerRequest(['url' => '/test']);

        $handler = new class () implements RequestHandlerInterface {
            public ServerRequestInterface $receivedRequest;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->receivedRequest = $request;

                return new Response();
            }
        };

        $middleware = new SpeculumAuthorizationMiddleware();
        $middleware->process($request, $handler);

        $this->assertNull($handler->receivedRequest->getAttribute('authorization'));
    }

    public function testPassesThroughWhenNonAuthorizationServiceAttribute(): void
    {
        $request = (new ServerRequest(['url' => '/test']))
            ->withAttribute('authorization', 'not-a-service');

        $handler = new class () implements RequestHandlerInterface {
            public ServerRequestInterface $receivedRequest;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->receivedRequest = $request;

                return new Response();
            }
        };

        $middleware = new SpeculumAuthorizationMiddleware();
        $middleware->process($request, $handler);

        $this->assertSame('not-a-service', $handler->receivedRequest->getAttribute('authorization'));
    }
}
