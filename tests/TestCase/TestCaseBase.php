<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase;

use Cake\Cache\Cache;
use Cake\Core\BasePlugin;
use Cake\Core\Plugin;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use Crustum\Speculum\Event\RecordingEventManager;
use Crustum\Speculum\Recording\WorkerFlushPolicy;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Storage\DatabaseEntriesRepository;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Crustum\Speculum\Watcher\ExceptionWatcher;
use ReflectionClass;
use Throwable;

/**
 * Base test case for Speculum plugin tests.
 */
abstract class TestCaseBase extends TestCase
{
    /**
     * @var \Crustum\Speculum\Storage\DatabaseEntriesRepository
     */
    protected DatabaseEntriesRepository $repository;

    /**
     * Soft plugin names loaded for this test (removed in tearDown).
     *
     * @var list<string>
     */
    protected array $softPluginsLoaded = [];

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DatabaseEntriesRepository('test', 100);
        Speculum::setRepository($this->repository);
        Speculum::flushEntries();
        Speculum::$updatesQueue = [];
        Speculum::$filterUsing = [];
        Speculum::$filterBatchUsing = [];
        Speculum::$tagUsing = [];
        Speculum::$afterStoringHooks = [];
        Speculum::$afterRecordingHook = null;
        Speculum::$hiddenRequestHeaders = [
            'authorization',
            'proxy-authorization',
            'php-auth-pw',
            'cookie',
            'set-cookie',
            'x-xsrf-token',
            'x-csrf-token',
        ];
        Speculum::$hiddenRequestParameters = [
            '*password*',
            '*token*',
            '*secret*',
            '*api_key*',
            '*apikey*',
        ];
        Speculum::$hiddenResponseParameters = [
            '*password*',
            '*token*',
            '*secret*',
        ];
        Speculum::$hiddenModelAttributes = [
            '*password*',
            '*token*',
            '*secret*',
        ];
        Speculum::auth(null);
        Speculum::startRecording(false);
        Speculum::$ignoreFrameworkEvents = true;
        ExceptionWatcher::resetRecorded();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        foreach ($this->softPluginsLoaded as $name) {
            if (Plugin::getCollection()->has($name)) {
                Plugin::getCollection()->remove($name);
            }
        }

        $this->softPluginsLoaded = [];

        Speculum::flushEntries();
        Speculum::$afterStoringHooks = [];
        Speculum::$afterRecordingHook = null;
        WatcherRegistry::clearExtensionPanels();
        $this->repository->clear();
        try {
            Cache::delete(Speculum::PAUSE_CACHE_KEY);
        } catch (Throwable) {
        }

        Speculum::stopRecording();
        WorkerFlushPolicy::reset();
        RecordingEventManager::uninstall();
        parent::tearDown();
    }

    /**
     * Mark a soft-dependency plugin as loaded for SoftFeature checks.
     *
     * @param string $name Plugin name (e.g. Crustum/Broadcasting).
     * @return void
     */
    protected function markSoftPluginLoaded(string $name): void
    {
        if (Plugin::isLoaded($name)) {
            return;
        }

        Plugin::getCollection()->add(new BasePlugin(['name' => $name]));
        $this->softPluginsLoaded[] = $name;
    }

    /**
     * Store queued entries and return all stored results.
     *
     * @return list<\Crustum\Speculum\Entry\EntryResult>
     */
    protected function loadSpeculumEntries(): array
    {
        $this->terminateSpeculum();

        return $this->repository->get(null, (new EntryQueryOptions())->limit(-1));
    }

    /**
     * Set the created timestamp for a stored Speculum entry via ORM.
     *
     * @param string $uuid Entry UUID.
     * @param \Cake\I18n\DateTime $created Created timestamp.
     * @return void
     */
    protected function setSpeculumEntryCreated(string $uuid, DateTime $created): void
    {
        $this->fetchTable('Crustum/Speculum.SpeculumEntries')->updateAll(
            ['created' => $created],
            ['uuid' => $uuid],
        );
    }

    /**
     * Persist the in-memory Speculum entry queue.
     *
     * @return void
     */
    public function terminateSpeculum(): void
    {
        Speculum::store($this->repository);
    }

    /**
     * Register watcher class names on WatcherRegistry for has() checks.
     *
     * @param list<class-string<\Crustum\Speculum\Watcher\Watcher>> $classes Watcher classes.
     * @return void
     */
    protected function registerWatcherClasses(array $classes): void
    {
        $property = (new ReflectionClass(WatcherRegistry::class))->getProperty('watchers');
        $property->setValue(null, $classes);
    }

    /**
     * Register a single watcher class on Speculum.
     *
     * @param class-string<\Crustum\Speculum\Watcher\Watcher> $class Watcher class name.
     * @return void
     */
    protected function registerWatcherClass(string $class): void
    {
        $this->registerWatcherClasses([$class]);
    }
}
