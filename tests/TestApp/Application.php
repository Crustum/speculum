<?php
declare(strict_types=1);

namespace TestApp;

use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\Routing\RouteBuilder;
use Override;

/**
 * Minimal test application for Crustum/Speculum integration tests.
 */
class Application extends BaseApplication
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function bootstrap(): void
    {
        parent::bootstrap();

        $this->addPlugin('Crustum/Speculum', [
            'bootstrap' => true,
            'routes' => true,
            'middleware' => true,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue
            ->add(new AssetMiddleware())
            ->add(new RoutingMiddleware($this))
            ->add(new BodyParserMiddleware());

        return $middlewareQueue;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function routes(RouteBuilder $routes): void
    {
        parent::routes($routes);
    }
}
