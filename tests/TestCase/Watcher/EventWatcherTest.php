<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\Event\Event;
use Cake\Event\EventManager;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\EventWatcher;

/**
 * Event watcher tests.
 */
class EventWatcherTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testEventWatcherRegistersAnyEvents(): void
    {
        $watcher = new EventWatcher(['enabled' => true]);
        $watcher->recordEvent('App.Demo.SomethingHappened');

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Event->value, $entry->type);
        $this->assertSame('App.Demo.SomethingHappened', $entry->content['name']);
    }

    /**
     * @return void
     */
    public function testEventWatcherStoresPayloads(): void
    {
        $watcher = new EventWatcher(['enabled' => true]);
        $watcher->recordEvent('App.Demo.Payload', ['Speculum', 'CakePHP', 'PHP']);

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Event->value, $entry->type);
        $this->assertSame(['Speculum', 'CakePHP', 'PHP'], $entry->content['payload']);
    }

    /**
     * @return void
     */
    public function testEventWatcherIgnoresFrameworkEventsByDefault(): void
    {
        Speculum::$ignoreFrameworkEvents = true;
        $watcher = new EventWatcher(['enabled' => true]);
        $watcher->recordEvent('Model.afterSave');
        $watcher->recordEvent('App.Custom.Event');

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('App.Custom.Event', $entries[0]->content['name']);
    }

    /**
     * @return void
     */
    public function testEventWatcherRespectsIgnoreOption(): void
    {
        $watcher = new EventWatcher([
            'enabled' => true,
            'ignore' => ['App.Ignored.*'],
        ]);
        $watcher->recordEvent('App.Ignored.Thing');
        $watcher->recordEvent('App.Kept.Thing');

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('App.Kept.Thing', $entries[0]->content['name']);
    }

    /**
     * @return void
     */
    public function testEventWatcherMaskStarRecordsMatchingDispatches(): void
    {
        Speculum::$ignoreFrameworkEvents = true;
        $watcher = new EventWatcher([
            'enabled' => true,
            'events' => ['*'],
        ]);
        $watcher->register();

        EventManager::instance()->dispatch(new Event('App.Demo.StarMask'));
        EventManager::instance()->dispatch(new Event('Model.afterSave'));

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('App.Demo.StarMask', $entries[0]->content['name']);
    }

    /**
     * @return void
     */
    public function testEventWatcherPrefixMaskRecordsMatchingDispatches(): void
    {
        Speculum::$ignoreFrameworkEvents = false;
        $watcher = new EventWatcher([
            'enabled' => true,
            'events' => ['Model.*'],
        ]);
        $watcher->register();

        EventManager::instance()->dispatch(new Event('Model.afterSave'));
        EventManager::instance()->dispatch(new Event('App.Other.Event'));

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('Model.afterSave', $entries[0]->content['name']);
    }

    /**
     * @return void
     */
    public function testEventWatcherExactNameStillRegistersListener(): void
    {
        $watcher = new EventWatcher([
            'enabled' => true,
            'events' => ['App.Exact.Name'],
        ]);
        $watcher->register();

        EventManager::instance()->dispatch(new Event('App.Exact.Name'));
        EventManager::instance()->dispatch(new Event('App.Other.Name'));

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('App.Exact.Name', $entries[0]->content['name']);
    }
}
