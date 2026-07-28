<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\Event\Event;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\ScheduleWatcher;
use stdClass;

/**
 * Schedule watcher coverage for Crustum Scheduling event shape.
 */
class ScheduleWatcherTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testRecordStoresScheduledTaskEntry(): void
    {
        $watcher = new ScheduleWatcher(['enabled' => true]);
        $watcher->record([
            'command' => 'dir',
            'description' => 'Test message',
            'expression' => '* * * * *',
            'timezone' => 'UTC',
            'exit_code' => 0,
        ]);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(EntryType::ScheduledTask->value, $entries[0]->type);
        $this->assertSame('dir', $entries[0]->content['command']);
        $this->assertSame('Test message', $entries[0]->content['description']);
        $this->assertSame('* * * * *', $entries[0]->content['expression']);
        $this->assertSame(0, $entries[0]->content['exit_code']);
    }

    /**
     * @return void
     */
    public function testRecordFromEventReadsSchedulingTaskPayload(): void
    {
        $task = new stdClass();
        $task->command = 'dir';
        $task->expression = '* * * * *';
        $task->timezone = 'UTC';
        $task->user = null;
        $task->output = 'NUL';
        $task->exitCode = 0;
        $task->skippedBecauseOverlapping = false;
        $task->shouldAppendOutput = false;

        $event = new Event('Scheduling.ScheduledTaskFinished', new stdClass(), [
            'event' => $task,
            'runtime' => 12.5,
        ]);

        $watcher = new ScheduleWatcher(['enabled' => true]);
        $watcher->recordFromEvent($event);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('dir', $entries[0]->content['command']);
        $this->assertSame('* * * * *', $entries[0]->content['expression']);
        $this->assertSame(0, $entries[0]->content['exit_code']);
    }

    /**
     * @return void
     */
    public function testRecordFromEventSkipsOverlappingTasks(): void
    {
        $task = new stdClass();
        $task->command = 'dir';
        $task->expression = '* * * * *';
        $task->skippedBecauseOverlapping = true;

        $event = new Event('Scheduling.ScheduledTaskFinished', new stdClass(), [
            'event' => $task,
            'runtime' => 1.0,
        ]);

        $watcher = new ScheduleWatcher(['enabled' => true]);
        $watcher->recordFromEvent($event);

        $this->assertSame([], $this->loadSpeculumEntries());
    }
}
