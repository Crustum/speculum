<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Speculum;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Recording\RequestPathFilter;
use Crustum\Speculum\Recording\WorkerFlushPolicy;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\CacheWatcher;
use Crustum\Speculum\Watcher\QueryWatcher;
use Crustum\Speculum\Watcher\Watcher;

/**
 * Speculum orchestration hook tests.
 */
class SpeculumTest extends TestCaseBase
{
    /**
     * @var int
     */
    private int $count = 0;

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        Speculum::$afterRecordingHook = null;
        Configure::write('Speculum.queue.worker_flush_interval', 0);
        Configure::write('Speculum.queue.worker_flush_limit', 2000);
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testShouldIgnoreRequestFiltersDebugKitPaths(): void
    {
        $this->assertTrue(RequestPathFilter::shouldIgnoreRequest(new ServerRequest([
            'url' => '/debug-kit/toolbar/abc',
        ])));
        $this->assertTrue(RequestPathFilter::shouldIgnoreRequest(new ServerRequest([
            'url' => '/debug-kit/panels/view/1.json',
        ])));
        $this->assertTrue(RequestPathFilter::shouldIgnoreRequest(new ServerRequest([
            'url' => '/debug_kit/js/toolbar.js',
        ])));
        $this->assertFalse(RequestPathFilter::shouldIgnoreRequest(new ServerRequest([
            'url' => '/posts',
        ])));
    }

    /**
     * @return void
     */
    public function testShouldIgnoreRequestFiltersRhythmPaths(): void
    {
        $this->assertTrue(RequestPathFilter::shouldIgnoreRequest(new ServerRequest([
            'url' => '/rhythm',
        ])));
        $this->assertTrue(RequestPathFilter::shouldIgnoreRequest(new ServerRequest([
            'url' => '/rhythm/dashboard',
        ])));
        $this->assertFalse(RequestPathFilter::shouldIgnoreRequest(new ServerRequest([
            'url' => '/posts',
        ])));
    }

    /**
     * @return void
     */
    public function testShouldIgnoreRequestFiltersMonitorPaths(): void
    {
        $this->assertTrue(RequestPathFilter::shouldIgnoreRequest(new ServerRequest([
            'url' => '/monitor',
        ])));
        $this->assertTrue(RequestPathFilter::shouldIgnoreRequest(new ServerRequest([
            'url' => '/monitor/dashboard',
        ])));
        $this->assertTrue(RequestPathFilter::shouldIgnoreRequest(new ServerRequest([
            'url' => '/monitor/jobs/pending',
        ])));
        $this->assertFalse(RequestPathFilter::shouldIgnoreRequest(new ServerRequest([
            'url' => '/posts',
        ])));
    }

    /**
     * @return void
     */
    public function testQueuedJobLifecyclePersistsWhenStackEmpty(): void
    {
        Speculum::stopRecording();
        Speculum::$entriesQueue = [];

        WorkerFlushPolicy::begin();
        $this->assertTrue(Speculum::isRecording());
        Speculum::recordEntry(EntryType::Job, IncomingEntry::make([
            'status' => 'pending',
            'name' => 'App\\Job\\FlushDemo',
            'connection' => 'default',
            'queue' => 'default',
            'data' => [],
        ]));
        $this->assertCount(1, Speculum::$entriesQueue);

        WorkerFlushPolicy::end(true);
        $this->assertSame([], Speculum::$entriesQueue);
        $this->assertFalse(Speculum::isRecording());

        $entries = $this->repository->get(null, (new EntryQueryOptions())->limit(-1));
        $this->assertCount(1, $entries);
        $this->assertSame('App\\Job\\FlushDemo', $entries[0]->content['name']);
    }

    /**
     * @return void
     */
    public function testQueuedJobFlushDefersUntilLimit(): void
    {
        Configure::write('Speculum.queue.worker_flush_interval', 60);
        Configure::write('Speculum.queue.worker_flush_limit', 2);

        Speculum::stopRecording();
        Speculum::$entriesQueue = [];

        WorkerFlushPolicy::begin();
        Speculum::recordEntry(EntryType::Job, IncomingEntry::make([
            'status' => 'pending',
            'name' => 'App\\Job\\BufferedOne',
            'connection' => 'default',
            'queue' => 'default',
            'data' => [],
        ]));
        WorkerFlushPolicy::end(true);
        $this->assertCount(1, Speculum::$entriesQueue);
        $this->assertFalse(Speculum::isRecording());

        WorkerFlushPolicy::begin();
        Speculum::recordEntry(EntryType::Job, IncomingEntry::make([
            'status' => 'pending',
            'name' => 'App\\Job\\BufferedTwo',
            'connection' => 'default',
            'queue' => 'default',
            'data' => [],
        ]));
        WorkerFlushPolicy::end(true);
        $this->assertSame([], Speculum::$entriesQueue);

        $entries = $this->repository->get(null, (new EntryQueryOptions())->limit(-1));
        $this->assertCount(2, $entries);
    }

    /**
     * @return void
     */
    public function testQueuedJobFlushDefersUntilInterval(): void
    {
        Configure::write('Speculum.queue.worker_flush_interval', 0.05);
        Configure::write('Speculum.queue.worker_flush_limit', 10000);

        Speculum::stopRecording();
        Speculum::$entriesQueue = [];

        WorkerFlushPolicy::begin();
        Speculum::recordEntry(EntryType::Job, IncomingEntry::make([
            'status' => 'pending',
            'name' => 'App\\Job\\TimedOne',
            'connection' => 'default',
            'queue' => 'default',
            'data' => [],
        ]));
        WorkerFlushPolicy::end(true);
        $this->assertCount(1, Speculum::$entriesQueue);

        usleep(60000);

        WorkerFlushPolicy::begin();
        Speculum::recordEntry(EntryType::Job, IncomingEntry::make([
            'status' => 'pending',
            'name' => 'App\\Job\\TimedTwo',
            'connection' => 'default',
            'queue' => 'default',
            'data' => [],
        ]));
        WorkerFlushPolicy::end(true);
        $this->assertSame([], Speculum::$entriesQueue);

        $entries = $this->repository->get(null, (new EntryQueryOptions())->limit(-1));
        $this->assertCount(2, $entries);
    }

    /**
     * @return void
     */
    public function testExtensionPanelAppearsInAvailableWatchers(): void
    {
        $watcherClass = new class extends Watcher {
            public function register(): void
            {
            }
        };

        $className = $watcherClass::class;
        Configure::write('Speculum.enabled', true);
        Configure::write('Speculum.watchers.' . $className, ['enabled' => true]);
        WatcherRegistry::registerExtensionPanel('mongo', $className);

        $available = WatcherRegistry::availableWatchers();
        $this->assertContains('mongo', $available);
        $this->assertSame($className, WatcherRegistry::extensionPanels()['mongo']);
    }

    /**
     * @return void
     */
    public function testDisabledWatchersAreHiddenFromAvailableWatchers(): void
    {
        $this->assertContains('cache', WatcherRegistry::availableWatchers());
        $this->assertContains('queries', WatcherRegistry::availableWatchers());

        Configure::write('Speculum.watchers.' . CacheWatcher::class, [
            'enabled' => false,
        ]);
        Configure::write('Speculum.watchers.' . QueryWatcher::class, false);

        $available = WatcherRegistry::availableWatchers();
        $this->assertNotContains('cache', $available);
        $this->assertNotContains('queries', $available);
        $this->assertContains('requests', $available);

        Configure::delete('Speculum.watchers.' . CacheWatcher::class);
        Configure::delete('Speculum.watchers.' . QueryWatcher::class);
    }

    /**
     * @return void
     */
    public function testRegisterApiResourceIsListedForRoutes(): void
    {
        WatcherRegistry::clearExtensionPanels();
        WatcherRegistry::registerApiResource('custom', [
            'plugin' => 'Acme/Example',
            'controller' => 'Example',
        ]);

        $this->assertSame([
            'custom' => [
                'plugin' => 'Acme/Example',
                'controller' => 'Example',
            ],
        ], WatcherRegistry::extensionApiResources());

        WatcherRegistry::clearExtensionPanels();
        $this->assertSame([], WatcherRegistry::extensionApiResources());
    }

    /**
     * @return void
     */
    public function testRegisterEntryResourceAndExtensionType(): void
    {
        WatcherRegistry::clearEntryResources();
        WatcherRegistry::clearExtensionPanels();
        WatcherRegistry::registerDefaultEntryResources();

        $queries = WatcherRegistry::entryResource('queries');
        $this->assertNotNull($queries);
        $this->assertSame(EntryType::Query->value, $queries->type);
        $this->assertNull(WatcherRegistry::entryResource('mail'));
        $this->assertNull(WatcherRegistry::entryResource('exceptions'));
        $this->assertNull(WatcherRegistry::entryResource('blazecast'));

        $watcherClass = new class extends Watcher {
            public function register(): void
            {
            }
        };
        $className = $watcherClass::class;

        Configure::write('Speculum.enabled', true);
        Configure::write('Speculum.watchers.' . $className, ['enabled' => true]);
        WatcherRegistry::registerExtensionPanel('widgets', $className, [
            'type' => 'widget',
        ]);

        $widgets = WatcherRegistry::entryResource('widgets');
        $this->assertNotNull($widgets);
        $this->assertSame('widget', $widgets->type);
        $this->assertSame($className, $widgets->watcher);
        $this->assertArrayNotHasKey('widgets', WatcherRegistry::extensionApiResources());

        WatcherRegistry::clearExtensionPanels();
        $this->assertNull(WatcherRegistry::entryResource('widgets'));
        $this->assertNotNull(WatcherRegistry::entryResource('queries'));

        WatcherRegistry::registerExtensionPanel('custom', $className, [
            'plugin' => 'Acme/Example',
            'controller' => 'Example',
        ]);
        $this->assertNull(WatcherRegistry::entryResource('custom'));
        $this->assertSame([
            'custom' => [
                'plugin' => 'Acme/Example',
                'controller' => 'Example',
            ],
        ], WatcherRegistry::extensionApiResources());
    }

    /**
     * @return void
     */
    public function testRecordEntryQueuesCustomType(): void
    {
        Speculum::startRecording(false);
        Speculum::recordEntry('mongo', IncomingEntry::make([
            'command' => 'find',
            'database' => 'demo',
            'collection' => 'files',
            'summary' => 'find demo files',
            'time' => '1.00',
            'slow' => false,
            'failed' => false,
            'payload' => [],
            'hash' => md5('find demo files'),
        ]));

        $this->assertCount(1, Speculum::$entriesQueue);
        $this->assertSame('mongo', Speculum::$entriesQueue[0]->type);
    }

    /**
     * @return void
     */
    public function testRunAfterRecordingCallback(): void
    {
        Speculum::afterRecording(function (Speculum $speculum, IncomingEntry $entry): void {
            $this->count++;
        });

        $watcher = new QueryWatcher(['enabled' => true, 'slow' => 1000]);
        $watcher->record('select 1', 1.0, 'test');
        $watcher->record('select 2', 1.0, 'test');

        $this->assertSame(2, $this->count);
    }

    /**
     * @return void
     */
    public function testAfterRecordingCallbackCanStoreAndFlush(): void
    {
        Speculum::afterRecording(function (Speculum $speculum, IncomingEntry $entry): void {
            if (count($speculum::$entriesQueue) > 1) {
                $speculum::store($this->repository);
            }
        });

        $watcher = new QueryWatcher(['enabled' => true, 'slow' => 1000]);
        $watcher->record('select 1', 1.0, 'test');
        $this->assertCount(1, Speculum::$entriesQueue);

        $watcher->record('select 2', 1.0, 'test');
        $this->assertCount(0, Speculum::$entriesQueue);

        $watcher->record('select 3', 1.0, 'test');
        $this->assertCount(1, Speculum::$entriesQueue);
    }

    /**
     * @return void
     */
    public function testRunAfterStoreCallback(): void
    {
        $storedEntries = null;
        $storedBatchId = null;
        Speculum::afterStoring(function (array $entries, $batchId) use (&$storedEntries, &$storedBatchId): void {
            $storedEntries = $entries;
            $storedBatchId = $batchId;
            $this->count += count($entries);
        });

        $watcher = new QueryWatcher(['enabled' => true, 'slow' => 1000]);
        $watcher->record('select 1', 1.0, 'test');
        $watcher->record('select 2', 1.0, 'test');

        $this->assertSame(0, $this->count);

        Speculum::store($this->repository);

        $this->assertSame(2, $this->count);
        $this->assertCount(2, $storedEntries);
        $this->assertSame(36, strlen((string)$storedBatchId));
        $this->assertInstanceOf(IncomingEntry::class, $storedEntries[0]);
        $this->assertCount(2, $this->repository->get(null, (new EntryQueryOptions())->limit(-1)));
    }
}
