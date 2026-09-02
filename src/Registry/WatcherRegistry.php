<?php
declare(strict_types=1);

namespace Crustum\Speculum\Registry;

use Authorization\Middleware\RequestAuthorizationMiddleware;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Queue\QueueManager;
use Crustum\Queue\Event\JobPushedEvent;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Watcher\AiWatcher;
use Crustum\Speculum\Watcher\AuthorizationWatcher;
use Crustum\Speculum\Watcher\BatchWatcher;
use Crustum\Speculum\Watcher\BlazeCastWatcher;
use Crustum\Speculum\Watcher\BroadcastWatcher;
use Crustum\Speculum\Watcher\CacheWatcher;
use Crustum\Speculum\Watcher\CommandWatcher;
use Crustum\Speculum\Watcher\EventWatcher;
use Crustum\Speculum\Watcher\ExceptionWatcher;
use Crustum\Speculum\Watcher\HttpClientWatcher;
use Crustum\Speculum\Watcher\LogWatcher;
use Crustum\Speculum\Watcher\MailWatcher;
use Crustum\Speculum\Watcher\ModelWatcher;
use Crustum\Speculum\Watcher\Mongo\CrustumMongoWatcher;
use Crustum\Speculum\Watcher\Mongo\MongoQueryLogWatcher;
use Crustum\Speculum\Watcher\Mongo\MongoWatcher;
use Crustum\Speculum\Watcher\NotificationWatcher;
use Crustum\Speculum\Watcher\QueryWatcher;
use Crustum\Speculum\Watcher\Queue\DereuromarkJobWatcher;
use Crustum\Speculum\Watcher\Queue\JobWatcher;
use Crustum\Speculum\Watcher\Queue\QueuesadillaJobWatcher;
use Crustum\Speculum\Watcher\RequestWatcher;
use Crustum\Speculum\Watcher\ScheduleWatcher;
use Crustum\Speculum\Watcher\SearchesWatcher;
use Crustum\Speculum\Watcher\VarDumpWatcher;
use Crustum\Speculum\Watcher\ViewWatcher;
use Crustum\Speculum\Watcher\Watcher;
use Queue\Model\Entity\QueuedJob;
use Throwable;

/**
 * Registers watchers, soft features, entry API resources, and extension SPA panels.
 */
final class WatcherRegistry
{
    /**
     * Registered watcher class names for this process.
     *
     * @var list<class-string<\Crustum\Speculum\Watcher\Watcher>>
     */
    private static array $watchers = [];

    /**
     * External SPA panel keys mapped to watcher classes (plugin / app panels).
     *
     * @var array<string, class-string<\Crustum\Speculum\Watcher\Watcher>>
     */
    private static array $extensionPanels = [];

    /**
     * Entry list/show resources served by Speculum EntryResourcesController.
     *
     * @var array<string, \Crustum\Speculum\Registry\EntryResource>
     */
    private static array $entryResources = [];

    /**
     * Whether default entry API resources have been registered.
     *
     * @var bool
     */
    private static bool $defaultEntryResourcesRegistered = false;

    /**
     * Extension API resources that use a foreign plugin controller (custom actions).
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $extensionApiResources = [];

    /**
     * Register configured watchers.
     *
     * @return void
     */
    public static function registerConfigured(): void
    {
        $watchers = Configure::read('Speculum.watchers', []);
        foreach ($watchers as $key => $watcher) {
            if (is_string($key) && $watcher === false) {
                continue;
            }

            if (is_array($watcher) && !($watcher['enabled'] ?? true)) {
                continue;
            }

            $class = is_string($key) ? $key : $watcher;
            if (!is_string($class)) {
                continue;
            }

            if (!class_exists($class)) {
                continue;
            }

            if (!is_a($class, Watcher::class, true)) {
                continue;
            }

            $options = is_array($watcher) ? $watcher : [];

            if (!self::shouldRegister($class)) {
                continue;
            }

            $instance = new $class($options);
            self::$watchers[] = $class;
            $instance->register();
        }
    }

    /**
     * Register a single watcher when Speculum is already running (extension plugins).
     *
     * @param class-string $class Watcher class name (validated at runtime).
     * @return void
     */
    public static function ensureRegistered(string $class): void
    {
        if (!Configure::read('Speculum.enabled', false)) {
            return;
        }

        if (in_array($class, self::$watchers, true)) {
            return;
        }

        if (!class_exists($class) || !is_a($class, Watcher::class, true)) {
            return;
        }

        if (!self::shouldRegister($class)) {
            return;
        }

        if (!self::isEnabled($class)) {
            return;
        }

        $watcher = Configure::read('Speculum.watchers.' . $class);
        $options = is_array($watcher) ? $watcher : [];
        $instance = new $class($options);
        self::$watchers[] = $class;
        $instance->register();
    }

    /**
     * Determine whether a watcher class is currently registered.
     *
     * @param class-string<\Crustum\Speculum\Watcher\Watcher> $class Watcher class.
     * @return bool
     */
    public static function has(string $class): bool
    {
        return in_array($class, self::$watchers, true);
    }

    /**
     * Determine whether a soft-dependency feature is usable.
     *
     * By default only checks the host dependency (plugin / extension / class).
     * Pass `$watcherClass` when the caller also needs that watcher enabled in config
     * (for example SPA nav). Registration and dispatch keep the one-argument form.
     *
     * @param \Crustum\Speculum\Enum\SoftFeature $feature Soft feature.
     * @param class-string<\Crustum\Speculum\Watcher\Watcher>|null $watcherClass Optional watcher to require enabled.
     * @return bool
     */
    public static function isSoftAvailable(SoftFeature $feature, ?string $watcherClass = null): bool
    {
        try {
            $available = match ($feature) {
                SoftFeature::Notification => Plugin::isLoaded('Crustum/Notification')
                    || Plugin::isLoaded('Notification'),
                SoftFeature::Batch => Plugin::isLoaded('Crustum/BatchQueue')
                    || Plugin::isLoaded('BatchQueue'),
                SoftFeature::Broadcasting => Plugin::isLoaded('Crustum/Broadcasting')
                    || Plugin::isLoaded('Broadcasting'),
                SoftFeature::BlazeCast => Plugin::isLoaded('Crustum/BlazeCast')
                    || Plugin::isLoaded('BlazeCast'),
                SoftFeature::Schedule => Plugin::isLoaded('Crustum/Scheduling')
                    || Plugin::isLoaded('Scheduling'),
                SoftFeature::Queuesadilla => Plugin::isLoaded('Josegonzalez/CakeQueuesadilla')
                    || Plugin::isLoaded('CakeQueuesadilla'),
                SoftFeature::CakeQueue => class_exists(QueueManager::class),
                SoftFeature::CrustumQueue => Plugin::isLoaded('Crustum/Queue')
                    || (
                        Plugin::isLoaded('Queue')
                        && class_exists(JobPushedEvent::class)
                    ),
                SoftFeature::DereuromarkQueue => class_exists(QueuedJob::class),
                SoftFeature::Mongo => extension_loaded('mongodb'),
                SoftFeature::CrustumMongo => class_exists('Crustum\\Mongo\\Database\\Driver\\MongoDriver'),
                SoftFeature::CakeDCAuth => class_exists('CakeDC\\Auth\\Rbac\\Rbac')
                    || Plugin::isLoaded('CakeDC/Auth')
                    || Plugin::isLoaded('CakeDC/Users'),
                SoftFeature::Ai => Plugin::isLoaded('Crustum/Ai') || Plugin::isLoaded('Ai'),
                SoftFeature::Authorization => class_exists(RequestAuthorizationMiddleware::class),
                SoftFeature::Explorator => Plugin::isLoaded('Crustum/Explorator')
                    || Plugin::isLoaded('Explorator'),
            };
        } catch (Throwable) {
            return false;
        }

        if (!$available) {
            return false;
        }

        return $watcherClass === null || self::isEnabled($watcherClass);
    }

    /**
     * Determine whether a watcher is enabled in Speculum config.
     *
     * @param class-string<\Crustum\Speculum\Watcher\Watcher> $class Watcher class.
     * @return bool
     */
    public static function isEnabled(string $class): bool
    {
        $watcher = Configure::read('Speculum.watchers.' . $class);
        if ($watcher === false || $watcher === null) {
            return false;
        }

        return !is_array($watcher) || ($watcher['enabled'] ?? true);
    }

    /**
     * Return the list of watchers available to the SPA navigation.
     *
     * Explicitly disabled watchers are omitted so the sidebar does not show a dead tab.
     *
     * @return list<string>
     */
    public static function availableWatchers(): array
    {
        $map = [
            'mail' => self::isNavVisible(MailWatcher::class),
            'exceptions' => self::isNavVisible(ExceptionWatcher::class),
            'logs' => self::isNavVisible(LogWatcher::class),
            'vardumps' => self::isNavVisible(VarDumpWatcher::class),
            'notifications' => self::isSoftAvailable(SoftFeature::Notification, NotificationWatcher::class),
            'jobs' => self::isNavVisible(JobWatcher::class)
                || self::isSoftAvailable(SoftFeature::Queuesadilla, QueuesadillaJobWatcher::class)
                || self::isSoftAvailable(SoftFeature::DereuromarkQueue, DereuromarkJobWatcher::class),
            'batches' => self::isSoftAvailable(SoftFeature::Batch, BatchWatcher::class),
            'broadcasts' => self::isSoftAvailable(SoftFeature::Broadcasting, BroadcastWatcher::class),
            'blazecast' => self::isSoftAvailable(SoftFeature::BlazeCast, BlazeCastWatcher::class),
            'events' => self::isNavVisible(EventWatcher::class),
            'cache' => self::isNavVisible(CacheWatcher::class),
            'queries' => self::isNavVisible(QueryWatcher::class),
            'models' => self::isNavVisible(ModelWatcher::class),
            'mongo' => self::isSoftAvailable(SoftFeature::Mongo, MongoWatcher::class),
            'mongo-queries' => self::isSoftAvailable(SoftFeature::CrustumMongo, CrustumMongoWatcher::class),
            'mongo-query-logs' => self::isSoftAvailable(SoftFeature::CrustumMongo, MongoQueryLogWatcher::class),
            'authorization' => self::isSoftAvailable(SoftFeature::CakeDCAuth, AuthorizationWatcher::class)
                || self::isSoftAvailable(SoftFeature::Authorization, AuthorizationWatcher::class),
            'ai' => self::isSoftAvailable(SoftFeature::Ai, AiWatcher::class),
            'requests' => self::isNavVisible(RequestWatcher::class),
            'views' => self::isNavVisible(ViewWatcher::class),
            'commands' => self::isNavVisible(CommandWatcher::class),
            'schedule' => self::isSoftAvailable(SoftFeature::Schedule, ScheduleWatcher::class),
            'searches' => self::isSoftAvailable(SoftFeature::Explorator, SearchesWatcher::class),
            'http-clients' => self::isNavVisible(HttpClientWatcher::class),
        ];

        $available = [];
        foreach ($map as $name => $enabled) {
            if ($enabled) {
                $available[] = $name;
            }
        }

        foreach (self::$extensionPanels as $navKey => $watcherClass) {
            if (self::isNavVisible($watcherClass) && !in_array($navKey, $available, true)) {
                $available[] = $navKey;
            }
        }

        return $available;
    }

    /**
     * Whether a core watcher should appear in SPA navigation.
     *
     * Explicit `false` / `enabled => false` hides the tab. Missing config keeps the tab
     * (sparse test setups). Soft tabs use {@see isSoftAvailable()} with a watcher class.
     *
     * @param class-string<\Crustum\Speculum\Watcher\Watcher> $class Watcher class.
     * @return bool
     */
    public static function isNavVisible(string $class): bool
    {
        $watcher = Configure::read('Speculum.watchers.' . $class);
        if ($watcher === false) {
            return false;
        }

        return !is_array($watcher) || ($watcher['enabled'] ?? true);
    }

    /**
     * Register first-party entry API resources (list/show via EntryResourcesController).
     *
     * Safe to call multiple times; defaults register once. Mail, exceptions, and BlazeCast stay on dedicated controllers.
     *
     * @return void
     */
    public static function registerDefaultEntryResources(): void
    {
        if (self::$defaultEntryResourcesRegistered) {
            return;
        }

        self::$defaultEntryResourcesRegistered = true;

        self::registerEntryResource('batches', BatchWatcher::class, EntryType::Batch->value, SoftFeature::Batch);
        self::registerEntryResource('broadcasts', BroadcastWatcher::class, EntryType::Broadcast->value, SoftFeature::Broadcasting);
        self::registerEntryResource('cache', CacheWatcher::class, EntryType::Cache->value);
        self::registerEntryResource('authorization', AuthorizationWatcher::class, EntryType::Authorization->value, SoftFeature::Authorization);
        self::registerEntryResource('authorization', AuthorizationWatcher::class, EntryType::CakeDCAuth->value, SoftFeature::CakeDCAuth);
        self::registerEntryResource('ai', AiWatcher::class, EntryType::Ai->value, SoftFeature::Ai);
        self::registerEntryResource('commands', CommandWatcher::class, EntryType::Command->value);
        self::registerEntryResource('events', EventWatcher::class, EntryType::Event->value);
        self::registerEntryResource('http-clients', HttpClientWatcher::class, EntryType::HttpClient->value);
        self::registerEntryResource('jobs', JobWatcher::class, EntryType::Job->value);
        self::registerEntryResource('logs', LogWatcher::class, EntryType::Log->value);
        self::registerEntryResource('models', ModelWatcher::class, EntryType::Model->value);
        self::registerEntryResource('mongo', MongoWatcher::class, EntryType::Mongo->value, SoftFeature::Mongo);
        self::registerEntryResource('mongo-queries', CrustumMongoWatcher::class, EntryType::MongoQuery->value, SoftFeature::CrustumMongo);
        self::registerEntryResource('mongo-query-logs', MongoQueryLogWatcher::class, EntryType::MongoQueryLog->value, SoftFeature::CrustumMongo);
        self::registerEntryResource('notifications', NotificationWatcher::class, EntryType::Notification->value, SoftFeature::Notification);
        self::registerEntryResource('queries', QueryWatcher::class, EntryType::Query->value);
        self::registerEntryResource('requests', RequestWatcher::class, EntryType::Request->value);
        self::registerEntryResource('schedule', ScheduleWatcher::class, EntryType::ScheduledTask->value, SoftFeature::Schedule);
        self::registerEntryResource('searches', SearchesWatcher::class, EntryType::Explorator->value, SoftFeature::Explorator);
        self::registerEntryResource('vardumps', VarDumpWatcher::class, EntryType::VarDump->value);
        self::registerEntryResource('views', ViewWatcher::class, EntryType::View->value);
    }

    /**
     * Register an entry API resource served by Speculum's EntryResourcesController.
     *
     * @param string $path URL segment under `/speculum/api` (for example `queries` or `mongo`).
     * @param class-string<\Crustum\Speculum\Watcher\Watcher> $watcherClass Watcher class for status checks.
     * @param list<string>|string $type Entry type value(s).
     * @param \Crustum\Speculum\Enum\SoftFeature|null $soft Soft feature gate for recording status.
     * @return void
     */
    public static function registerEntryResource(
        string $path,
        string $watcherClass,
        string|array $type,
        ?SoftFeature $soft = null,
    ): void {
        $path = trim($path, '/');
        if ($path === '') {
            return;
        }

        $types = is_array($type) ? $type : [$type];
        $softs = $soft instanceof SoftFeature ? [$soft] : [];

        if (isset(self::$entryResources[$path])) {
            $existing = self::$entryResources[$path];
            $existingTypes = is_array($existing->type) ? $existing->type : [$existing->type];
            $existingSofts = is_array($existing->soft) ? $existing->soft : ($existing->soft === null ? [] : [$existing->soft]);
            $types = array_values(array_unique([...$existingTypes, ...$types]));
            foreach ($existingSofts as $existingSoft) {
                if (!in_array($existingSoft, $softs, true)) {
                    $softs[] = $existingSoft;
                }
            }
        }

        $mergedType = count($types) === 1 ? $types[0] : $types;
        $mergedSoft = match (count($softs)) {
            0 => null,
            1 => $softs[0],
            default => $softs,
        };

        self::$entryResources[$path] = new EntryResource($path, $mergedType, $watcherClass, $mergedSoft);
    }

    /**
     * Return a registered entry resource by path, or null.
     *
     * @param string $path URL segment.
     * @return \Crustum\Speculum\Registry\EntryResource|null
     */
    public static function entryResource(string $path): ?EntryResource
    {
        $path = trim($path, '/');

        return self::$entryResources[$path] ?? null;
    }

    /**
     * Return all registered entry API resources.
     *
     * @return array<string, \Crustum\Speculum\Registry\EntryResource>
     */
    public static function entryResources(): array
    {
        return self::$entryResources;
    }

    /**
     * Register an external SPA panel key backed by a watcher class.
     *
     * Safe to call after Speculum start; registers the watcher immediately when Speculum is enabled.
     *
     * API options (pick one):
     * - `type` (+ optional `soft`): Speculum `EntryResourcesController` serves list/show.
     * - `plugin` + `controller`: your plugin controller (extend `EntryController`) for list/show and any
     *   custom actions (resolve, preview, download, etc.), connected on the sibling `/speculum/api` scope.
     *
     * @param string $navKey SPA nav / meta key (for example `mongo`).
     * @param class-string<\Crustum\Speculum\Watcher\Watcher> $watcherClass Watcher class.
     * @param array{type?: string|list<string>, soft?: \Crustum\Speculum\Enum\SoftFeature, plugin?: string, controller?: string}|null $api
     * @return void
     */
    public static function registerExtensionPanel(string $navKey, string $watcherClass, ?array $api = null): void
    {
        self::$extensionPanels[$navKey] = $watcherClass;
        self::ensureRegistered($watcherClass);

        if ($api === null) {
            return;
        }

        if (isset($api['plugin']) || isset($api['controller'])) {
            self::registerApiResource($navKey, $api);

            return;
        }

        if (array_key_exists('type', $api)) {
            /** @var list<string>|string $type */
            $type = $api['type'];
            $soft = $api['soft'] ?? null;
            self::registerEntryResource(
                $navKey,
                $watcherClass,
                $type,
                $soft instanceof SoftFeature ? $soft : null,
            );
        }
    }

    /**
     * Register an extension API resource with a foreign plugin controller.
     *
     * Use when the panel needs custom actions beyond list/show (same class of need as Speculum mail
     * preview/download or exception resolve). For list/show only, prefer `registerEntryResource` / `type`.
     *
     * @param string $path URL segment under `/speculum/api` (for example `widgets`).
     * @param array<string, mixed> $defaults Route defaults including `plugin` and `controller`.
     * @return void
     */
    public static function registerApiResource(string $path, array $defaults): void
    {
        $path = trim($path, '/');
        if ($path === '') {
            return;
        }

        self::$extensionApiResources[$path] = $defaults;
    }

    /**
     * Return extension API resources that use a foreign plugin controller.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function extensionApiResources(): array
    {
        return self::$extensionApiResources;
    }

    /**
     * Return registered extension panel nav keys.
     *
     * @return array<string, class-string<\Crustum\Speculum\Watcher\Watcher>>
     */
    public static function extensionPanels(): array
    {
        return self::$extensionPanels;
    }

    /**
     * Clear registered extension panels and foreign-controller API resources (tests / process reset).
     *
     * Also removes entry resources whose path matches an extension panel key.
     *
     * @return void
     */
    public static function clearExtensionPanels(): void
    {
        foreach (array_keys(self::$extensionPanels) as $navKey) {
            unset(self::$entryResources[$navKey]);
        }

        self::$extensionPanels = [];
        self::$extensionApiResources = [];
    }

    /**
     * Clear all entry API resources including defaults (tests).
     *
     * @return void
     */
    public static function clearEntryResources(): void
    {
        self::$entryResources = [];
        self::$defaultEntryResourcesRegistered = false;
    }

    /**
     * Determine whether the given watcher class should be registered.
     *
     * @param string $class Watcher class.
     * @return bool
     */
    private static function shouldRegister(string $class): bool
    {
        $soft = [
            BatchWatcher::class => SoftFeature::Batch,
            BroadcastWatcher::class => SoftFeature::Broadcasting,
            BlazeCastWatcher::class => SoftFeature::BlazeCast,
            NotificationWatcher::class => SoftFeature::Notification,
            QueuesadillaJobWatcher::class => SoftFeature::Queuesadilla,
            DereuromarkJobWatcher::class => SoftFeature::DereuromarkQueue,
            MongoWatcher::class => SoftFeature::Mongo,
            CrustumMongoWatcher::class => SoftFeature::CrustumMongo,
            MongoQueryLogWatcher::class => SoftFeature::CrustumMongo,
            AuthorizationWatcher::class => SoftFeature::CakeDCAuth,
            AiWatcher::class => SoftFeature::Ai,
            ScheduleWatcher::class => SoftFeature::Schedule,
            SearchesWatcher::class => SoftFeature::Explorator,
        ];

        if (isset($soft[$class])) {
            return self::isSoftAvailable($soft[$class]);
        }

        return true;
    }
}
