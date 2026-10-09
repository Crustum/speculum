<?php
declare(strict_types=1);

namespace Crustum\Speculum;

use Authorization\Middleware\RequestAuthorizationMiddleware;
use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\RoutingMiddleware;
use Crustum\PluginManifest\Manifest\ManifestInterface;
use Crustum\PluginManifest\Manifest\ManifestTrait;
use Crustum\PluginManifest\Manifest\Tag;
use Crustum\Speculum\Command\ClearCommand;
use Crustum\Speculum\Command\ListCommand;
use Crustum\Speculum\Command\McpCommand;
use Crustum\Speculum\Command\PauseCommand;
use Crustum\Speculum\Command\PruneCommand;
use Crustum\Speculum\Command\ResumeCommand;
use Crustum\Speculum\Command\ShowCommand;
use Crustum\Speculum\Contract\ClearableRepository;
use Crustum\Speculum\Contract\EntriesRepository;
use Crustum\Speculum\Contract\PrunableRepository;
use Crustum\Speculum\Mcp\SpeculumServer;
use Crustum\Speculum\Middleware\SpeculumAuthorizationMiddleware;
use Crustum\Speculum\Middleware\SpeculumRecordingMiddleware;
use Crustum\Speculum\Middleware\SpeculumRequestCaptureMiddleware;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Storage\DatabaseEntriesRepository;
use Crustum\Speculum\Watcher\RequestWatcher;
use Override;
use Throwable;

/**
 * CakePHP Speculum plugin.
 *
 * @uses \Crustum\PluginManifest\Manifest\ManifestTrait
 */
class SpeculumPlugin extends BasePlugin implements ManifestInterface
{
    use ManifestTrait;

    /**
     * Whether plugin routes are enabled.
     *
     * @var bool
     */
    protected bool $routesEnabled = true;

    /**
     * Whether plugin bootstrap is enabled.
     *
     * @var bool
     */
    protected bool $bootstrapEnabled = true;

    /**
     * Whether plugin console commands are enabled.
     *
     * @var bool
     */
    protected bool $consoleEnabled = true;

    /**
     * Whether plugin middleware is enabled.
     *
     * @var bool
     */
    protected bool $middlewareEnabled = true;

    /**
     * @inheritDoc
     */
    #[Override]
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        if (!Configure::check('Speculum')) {
            if (file_exists(CONFIG . 'speculum.php')) {
                Configure::load('speculum', 'default');
            } elseif (file_exists($this->getConfigPath() . 'speculum.php')) {
                Configure::load('Crustum/Speculum.speculum', 'default', false);
            }
        }

        if (Configure::read('Speculum.early_timer_start', false)) {
            Speculum::markRequestStart(true);

            $app->getEventManager()->on('Application.buildContainer', static function (): void {
                Speculum::markRequestStart();
            });
        }

        if ((bool)Configure::read('Speculum.enabled', false)) {
            $local = Configure::read('Mcp.local', []);
            $local = is_array($local) ? $local : [];
            $local['cake-speculum'] = SpeculumServer::class;
            Configure::write('Mcp.local', $local);
        }

        WatcherRegistry::registerDefaultEntryResources();
    }

    /**
     * Register Speculum services.
     *
     * Repository construction is deferred and guarded so Cake console
     * (for example `manifest install`) can run before Speculum migrations exist.
     *
     * @param \Cake\Core\ContainerInterface $container DI container.
     * @return void
     */
    public function services(ContainerInterface $container): void
    {
        $connection = (string)Configure::read('Speculum.storage.database.connection', 'default');
        $chunk = (int)Configure::read('Speculum.storage.database.chunk', 1000);

        $container->addShared(DatabaseEntriesRepository::class, fn(): DatabaseEntriesRepository => new DatabaseEntriesRepository($connection, $chunk));

        $container->addShared(EntriesRepository::class, fn() => $container->get(DatabaseEntriesRepository::class));
        $container->addShared(ClearableRepository::class, fn() => $container->get(DatabaseEntriesRepository::class));
        $container->addShared(PrunableRepository::class, fn() => $container->get(DatabaseEntriesRepository::class));

        try {
            $repository = $container->get(DatabaseEntriesRepository::class);
            Speculum::setRepository($repository);
            Speculum::start();
        } catch (Throwable) {
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $authorizationDecorator = new SpeculumAuthorizationMiddleware();

        $requestAuthorizationClass = RequestAuthorizationMiddleware::class;

        if (class_exists($requestAuthorizationClass)) {
            try {
                $middlewareQueue->insertBefore($requestAuthorizationClass, $authorizationDecorator);
            } catch (Throwable) {
            }
        }

        if (!$this->isRequestWatcherDisabled()) {
            return $this->pushRecordingMiddleware($middlewareQueue);
        }

        return $middlewareQueue;
    }

    /**
     * Whether the request watcher is explicitly disabled via configuration.
     *
     * Missing configuration means enabled (default-on); only `false` or
     * `['enabled' => false]` skips the recording middlewares.
     *
     * @return bool
     */
    protected function isRequestWatcherDisabled(): bool
    {
        $watcher = Configure::read('Speculum.watchers.' . RequestWatcher::class);

        return $watcher === false || (is_array($watcher) && !($watcher['enabled'] ?? true));
    }

    /**
     * Add the early recording and late capture middlewares to the queue.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue Queue.
     * @return \Cake\Http\MiddlewareQueue
     */
    protected function pushRecordingMiddleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $recording = new SpeculumRecordingMiddleware();
        $capture = new SpeculumRequestCaptureMiddleware();

        $requestAuthorizationClass = RequestAuthorizationMiddleware::class;
        $errorHandlerClass = ErrorHandlerMiddleware::class;
        $placed = false;

        if (class_exists($requestAuthorizationClass)) {
            try {
                $middlewareQueue->insertBefore($requestAuthorizationClass, $recording);
                $placed = true;
            } catch (Throwable) {
            }
        }

        if (!$placed && class_exists($errorHandlerClass)) {
            try {
                $middlewareQueue->insertBefore($errorHandlerClass, $recording);
                $placed = true;
            } catch (Throwable) {
            }
        }

        if (!$placed) {
            $middlewareQueue->add($recording);
        }

        if (class_exists(RoutingMiddleware::class)) {
            try {
                $middlewareQueue->insertAfter(RoutingMiddleware::class, $capture);

                return $middlewareQueue;
            } catch (Throwable) {
            }
        }

        return $middlewareQueue->add($capture);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands->add('speculum clear', ClearCommand::class);
        $commands->add('speculum list', ListCommand::class);
        $commands->add('speculum show', ShowCommand::class);
        $commands->add('speculum pause', PauseCommand::class);
        $commands->add('speculum resume', ResumeCommand::class);
        $commands->add('speculum prune', PruneCommand::class);
        $commands->add('speculum mcp', McpCommand::class);

        return $commands;
    }

    /**
     * Get the manifest for the plugin.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function manifest(): array
    {
        $pluginPath = dirname(__DIR__);

        return array_merge(
            static::manifestMigrations(
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'Migrations',
            ),
            static::manifestConfig(
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'speculum.php',
                CONFIG . 'speculum.php',
                false,
            ),
            static::manifestWebroot(
                $pluginPath . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR . 'frontend',
                WWW_ROOT . 'speculum',
            ),
            static::manifestBootstrapAppend(
                "if (file_exists(CONFIG . 'speculum.php')) {\n    Configure::load('speculum', 'default');\n}",
                '// Speculum Plugin Configuration',
            ),
            static::manifestDependencies([
                'Crustum/Mcp' => [
                    'required' => true,
                    'tags' => [Tag::CONFIG, Tag::BOOTSTRAP],
                    'reason' => 'MCP runtime for cake-speculum (Registrar, ContainerInvoker, mcp start)',
                ],
            ]),
            static::manifestStarRepo('Crustum/speculum'),
        );
    }
}
