<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Listener;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Event\SpeculumFlushEvent;
use Crustum\Speculum\Listener\StorageListener;
use Crustum\Speculum\Recording\WorkerFlushPolicy;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;

/**
 * `Speculum.flush` events persist the queue without direct Speculum::store() calls.
 */
class StorageListenerFlushEventTest extends TestCaseBase
{
    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $manager = EventManager::instance();
        $manager->off(SpeculumFlushEvent::class);
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testTypedFlushEventStoresQueuedEntries(): void
    {
        StorageListener::register();
        $stored = 0;
        Speculum::afterStoring(function () use (&$stored): void {
            $stored++;
        });

        Speculum::recordEntry('tool', IncomingEntry::make(['name' => 'flush-asap']));

        EventManager::instance()->dispatch(new SpeculumFlushEvent());

        $this->assertSame(1, $stored);
        $this->assertSame([], Speculum::$entriesQueue);
        $this->assertCount(1, $this->loadSpeculumEntries());
    }

    /**
     * @return void
     */
    public function testPlainNameFlushEventStoresQueuedEntries(): void
    {
        StorageListener::register();
        Speculum::recordEntry('tool', IncomingEntry::make(['name' => 'flush-by-name']));

        EventManager::instance()->dispatch(new Event(SpeculumFlushEvent::FLUSH));

        $this->assertSame([], Speculum::$entriesQueue);
        $this->assertCount(1, $this->loadSpeculumEntries());
    }

    /**
     * @return void
     */
    public function testClassKeyDispatchStoresQueuedEntries(): void
    {
        StorageListener::register();
        $seen = 0;
        EventManager::instance()->on(SpeculumFlushEvent::class, function () use (&$seen): void {
            $seen++;
        });
        Speculum::recordEntry('tool', IncomingEntry::make(['name' => 'flush-by-class']));

        EventManager::instance()->dispatch(new Event(SpeculumFlushEvent::class));

        $this->assertSame(1, $seen);
        $this->assertSame([], Speculum::$entriesQueue);
        $this->assertCount(1, $this->loadSpeculumEntries());
    }

    /**
     * @return void
     */
    public function testFlushWithEmptyQueueDoesNotStore(): void
    {
        StorageListener::register();
        $stored = 0;
        Speculum::afterStoring(function () use (&$stored): void {
            $stored++;
        });

        EventManager::instance()->dispatch(new SpeculumFlushEvent());

        $this->assertSame(0, $stored);
    }

    /**
     * @return void
     */
    public function testThrottledFlushDefersToWorkerPolicy(): void
    {
        StorageListener::register();
        WorkerFlushPolicy::reset();
        $previousInterval = Configure::read('Speculum.queue.worker_flush_interval');
        Configure::write('Speculum.queue.worker_flush_interval', 60);

        try {
            Speculum::recordEntry('tool', IncomingEntry::make(['name' => 'flush-throttled']));

            EventManager::instance()->dispatch(new SpeculumFlushEvent(['throttled' => true]));

            $this->assertCount(1, Speculum::$entriesQueue);
        } finally {
            Configure::write('Speculum.queue.worker_flush_interval', $previousInterval);
            WorkerFlushPolicy::reset();
        }
    }
}
