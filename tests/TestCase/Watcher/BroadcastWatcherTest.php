<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\Event\Event;
use Cake\Event\EventManager;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\BroadcastWatcher;

/**
 * Broadcast watcher coverage for Crustum Broadcasting soft-dep.
 */
class BroadcastWatcherTest extends TestCaseBase
{
    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->markSoftPluginLoaded('Crustum/Broadcasting');
    }

    /**
     * @return void
     */
    public function testBroadcastWatcherRecordsSentEvent(): void
    {
        $watcher = new BroadcastWatcher(['enabled' => true]);
        $watcher->register();

        EventManager::instance()->dispatch(new Event('Broadcasting.sent', null, [
            'channels' => ['posts', 'private-user.1'],
            'event' => 'PostCreated',
            'payload' => ['id' => 9],
            'connection' => 'default',
            'queued' => false,
        ]));

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(EntryType::Broadcast->value, $entries[0]->type);
        $this->assertSame('PostCreated', $entries[0]->content['event']);
        $this->assertSame(['posts', 'private-user.1'], $entries[0]->content['channels']);
        $this->assertSame('default', $entries[0]->content['connection']);
        $this->assertFalse($entries[0]->content['queued']);
        $this->assertSame(['id' => 9], $entries[0]->content['payload']);
    }
}
