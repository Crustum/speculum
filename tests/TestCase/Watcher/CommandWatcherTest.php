<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\Console\Arguments;
use Cake\Event\Event;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\CommandWatcher;
use stdClass;

/**
 * Command watcher tests.
 */
class CommandWatcherTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testCommandWatcherRegisterEntry(): void
    {
        $watcher = new CommandWatcher(['enabled' => true]);
        $watcher->record('speculum:test-command', [], 0);

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Command->value, $entry->type);
        $this->assertSame('speculum:test-command', $entry->content['command']);
        $this->assertSame(0, $entry->content['exit_code']);
        $this->assertIsInt($entry->content['exit_code']);
    }

    /**
     * @return void
     */
    public function testCommandWatcherReadsResultFromEventData(): void
    {
        $watcher = new CommandWatcher(['enabled' => true]);
        $args = new Arguments(['demo'], ['verbose' => true], ['demo']);
        $event = new Event('Command.afterExecute', new stdClass(), [
            'args' => $args,
            'io' => new stdClass(),
            'result' => 0,
        ]);

        $watcher->recordFromEvent($event);

        $entries = $this->loadSpeculumEntries();
        $this->assertSame(0, $entries[0]->content['exit_code']);
        $this->assertSame(['demo'], $entries[0]->content['arguments']);
        $this->assertSame(['verbose' => true], $entries[0]->content['options']);
    }

    /**
     * @return void
     */
    public function testCommandWatcherRespectsIgnorePatterns(): void
    {
        $watcher = new CommandWatcher([
            'enabled' => true,
            'ignore' => ['speculum:*'],
        ]);
        $watcher->record('speculum:clear', [], 0);
        $watcher->record('app:demo', ['x' => 1], 0);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('app:demo', $entries[0]->content['command']);
    }

    /**
     * @return void
     */
    public function testCommandWatcherRecordsDurationAndSlowTag(): void
    {
        $watcher = new CommandWatcher([
            'enabled' => true,
            'slow' => 1,
        ]);
        $command = new stdClass();
        $args = new Arguments(['demo'], [], ['demo']);

        $watcher->markStartedFromEvent(new Event('Command.beforeExecute', $command, [
            'args' => $args,
            'io' => new stdClass(),
        ]));
        usleep(2000);
        $watcher->recordFromEvent(new Event('Command.afterExecute', $command, [
            'args' => $args,
            'io' => new stdClass(),
            'result' => 0,
        ]));

        $this->assertNotEmpty(Speculum::$entriesQueue);
        $this->assertIsInt(Speculum::$entriesQueue[0]->content['duration']);
        $this->assertGreaterThanOrEqual(1, Speculum::$entriesQueue[0]->content['duration']);
        $this->assertTrue(Speculum::$entriesQueue[0]->content['slow']);
        $this->assertContains('slow', Speculum::$entriesQueue[0]->tags);
    }

    /**
     * @return void
     */
    public function testCommandWatcherRecordRespectsSlowThreshold(): void
    {
        $watcher = new CommandWatcher([
            'enabled' => true,
            'slow' => 500,
        ]);
        $watcher->record('app:fast', [], 0, [], 50);

        $this->assertFalse(Speculum::$entriesQueue[0]->content['slow']);
        $this->assertNotContains('slow', Speculum::$entriesQueue[0]->tags);
        $this->assertSame(50, Speculum::$entriesQueue[0]->content['duration']);
    }
}
