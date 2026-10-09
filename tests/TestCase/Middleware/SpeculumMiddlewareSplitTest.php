<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Middleware;

use Cake\Core\Configure;
use Cake\Http\MiddlewareQueue;
use Cake\Http\Response;
use Cake\Http\Runner;
use Cake\Http\ServerRequest;
use Cake\Routing\Middleware\RoutingMiddleware;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Middleware\SpeculumRecordingMiddleware;
use Crustum\Speculum\Middleware\SpeculumRequestCaptureMiddleware;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\SpeculumPlugin;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\RequestWatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use stdClass;

/**
 * Early/late middleware split tests.
 */
class SpeculumMiddlewareSplitTest extends TestCaseBase
{
    /**
     * @var mixed
     */
    protected mixed $previousEnabled;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->previousEnabled = Configure::read('Speculum.enabled');
        Configure::write('Speculum.enabled', true);
        $this->registerWatcherClass(RequestWatcher::class);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        Configure::write('Speculum.enabled', $this->previousEnabled);
        Configure::delete('Speculum.watchers.' . RequestWatcher::class);

        parent::tearDown();
    }

    /**
     * Run a middleware queue for one request.
     *
     * @param \Cake\Http\MiddlewareQueue $queue Queue.
     * @param \Cake\Http\ServerRequest $request Request.
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function runQueue(MiddlewareQueue $queue, ServerRequest $request): ResponseInterface
    {
        return (new Runner())->run($queue, $request);
    }

    /**
     * Terminal handler returning a fixed response.
     *
     * @param int $status Status code.
     * @return \Psr\Http\Server\MiddlewareInterface
     */
    protected function terminal(int $status = 200): MiddlewareInterface
    {
        return new class ($status) implements MiddlewareInterface {
            /**
             * @param int $status Status code.
             */
            public function __construct(private readonly int $status)
            {
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                return (new Response())->withStatus($this->status)->withStringBody('ok');
            }
        };
    }

    /**
     * Stub routing middleware attaching routing params.
     *
     * @param array<string, mixed> $params Routing params.
     * @return \Psr\Http\Server\MiddlewareInterface
     */
    protected function routingStub(array $params): MiddlewareInterface
    {
        return new class ($params) implements MiddlewareInterface {
            /**
             * @param array<string, mixed> $params Routing params.
             */
            public function __construct(private readonly array $params)
            {
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                return $handler->handle($request->withAttribute('params', $this->params));
            }
        };
    }

    /**
     * @return void
     */
    public function testRoutedRequestRecordedOnceWithController(): void
    {
        $queue = new MiddlewareQueue();
        $queue->add(new SpeculumRecordingMiddleware());
        $queue->add($this->routingStub([
            'controller' => 'Users',
            'action' => 'edit',
            'plugin' => null,
            'prefix' => 'Admin',
            'pass' => ['5'],
        ]));
        $queue->add(new SpeculumRequestCaptureMiddleware());
        $queue->add($this->terminal());

        $request = new ServerRequest(['url' => '/admin/users/edit/5']);
        $this->runQueue($queue, $request);

        $entries = $this->repository->get(
            EntryType::Request->value,
            new EntryQueryOptions(),
        );

        $this->assertCount(1, $entries);
        $this->assertSame('Admin:Users:edit', $entries[0]->content['controller_action']);
        $this->assertTrue(Speculum::isRequestEntryRecorded());
    }

    /**
     * @return void
     */
    public function testShortCircuitFallsBackToUnroutedEntry(): void
    {
        $queue = new MiddlewareQueue();
        $queue->add(new SpeculumRecordingMiddleware());
        $queue->add($this->terminal(302));

        $request = new ServerRequest(['url' => '/admin']);
        $response = $this->runQueue($queue, $request);

        $this->assertSame(302, $response->getStatusCode());

        $entries = $this->repository->get(
            EntryType::Request->value,
            new EntryQueryOptions(),
        );

        $this->assertCount(1, $entries);
        $this->assertArrayNotHasKey('controller_action', $entries[0]->content);
    }

    /**
     * @return void
     */
    public function testExceptionRecordsFallbackAndRethrows(): void
    {
        $queue = new MiddlewareQueue();
        $queue->add(new SpeculumRecordingMiddleware());
        $queue->add(new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                throw new RuntimeException('Boom');
            }
        });

        $request = new ServerRequest(['url' => '/broken']);

        try {
            $this->runQueue($queue, $request);
            $this->fail('Exception was not rethrown.');
        } catch (RuntimeException) {
        }

        $entries = $this->repository->get(
            EntryType::Request->value,
            new EntryQueryOptions(),
        );

        $this->assertCount(1, $entries);
        $this->assertSame(500, $entries[0]->content['response_status']);
    }

    /**
     * @return void
     */
    public function testWorkerReuseResetsStaleState(): void
    {
        Speculum::$entriesQueue = [
            IncomingEntry::make(['message' => 'stale'])->type(EntryType::Log->value),
        ];
        Speculum::auth(new stdClass());
        $oldStartedAt = Speculum::requestStartedAt();

        $queue = new MiddlewareQueue();
        $queue->add(new SpeculumRecordingMiddleware());
        $queue->add($this->terminal());

        $this->runQueue($queue, new ServerRequest(['url' => '/fresh']));

        $this->assertNull(Speculum::authenticatedUser());
        $this->assertNotSame($oldStartedAt, Speculum::requestStartedAt());

        $logs = $this->repository->get(
            EntryType::Log->value,
            new EntryQueryOptions(),
        );
        $this->assertCount(0, $logs);

        $requests = $this->repository->get(
            EntryType::Request->value,
            new EntryQueryOptions(),
        );
        $this->assertCount(1, $requests);
    }

    /**
     * @return void
     */
    public function testPluginSkipsRecordingMiddlewareWhenWatcherDisabled(): void
    {
        Configure::write('Speculum.watchers.' . RequestWatcher::class, false);

        $queue = (new SpeculumPlugin())->middleware(new MiddlewareQueue());

        $classes = [];
        foreach ($queue as $middleware) {
            $classes[] = $middleware::class;
        }

        $this->assertNotContains(SpeculumRecordingMiddleware::class, $classes);
        $this->assertNotContains(SpeculumRequestCaptureMiddleware::class, $classes);
    }

    /**
     * @return void
     */
    public function testPluginSkipsRecordingMiddlewareWhenWatcherFlagOff(): void
    {
        Configure::write('Speculum.watchers.' . RequestWatcher::class, ['enabled' => false]);

        $queue = (new SpeculumPlugin())->middleware(new MiddlewareQueue());

        $classes = [];
        foreach ($queue as $middleware) {
            $classes[] = $middleware::class;
        }

        $this->assertNotContains(SpeculumRecordingMiddleware::class, $classes);
        $this->assertNotContains(SpeculumRequestCaptureMiddleware::class, $classes);
    }

    /**
     * @return void
     */
    public function testPluginAddsRecordingMiddlewareWhenWatcherEnabled(): void
    {
        Configure::write('Speculum.watchers.' . RequestWatcher::class, ['enabled' => true]);

        $queue = (new SpeculumPlugin())->middleware(new MiddlewareQueue());

        $classes = [];
        foreach ($queue as $middleware) {
            $classes[] = $middleware::class;
        }

        $this->assertContains(SpeculumRecordingMiddleware::class, $classes);
        $this->assertContains(SpeculumRequestCaptureMiddleware::class, $classes);
    }

    /**
     * @return void
     */
    public function testPluginWiresCaptureAfterRouting(): void
    {
        $routing = $this->createStub(RoutingMiddleware::class);

        $queue = new MiddlewareQueue();
        $queue->add($routing);

        $queue = (new SpeculumPlugin())->middleware($queue);

        $classes = [];
        foreach ($queue as $middleware) {
            $classes[] = $middleware::class;
        }

        $routingPosition = array_search($routing::class, $classes, true);
        $capturePosition = array_search(SpeculumRequestCaptureMiddleware::class, $classes, true);
        $earlyPosition = array_search(SpeculumRecordingMiddleware::class, $classes, true);

        $this->assertNotFalse($routingPosition);
        $this->assertNotFalse($capturePosition);
        $this->assertNotFalse($earlyPosition);
        $this->assertSame($routingPosition + 1, $capturePosition);
    }
}
