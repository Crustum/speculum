<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Speculum;
use DateTimeZone;

/**
 * Soft watcher for Crustum Scheduling (and legacy schedule event names).
 */
class ScheduleWatcher extends Watcher
{
    /**
     * @inheritDoc
     */
    public function register(): void
    {
        $eventNames = [
            'Scheduling.ScheduledTaskFinished',
            'Scheduling.ScheduledBackgroundTaskFinished',
            'Schedule.taskFinished',
            'Scheduling.taskFinished',
            'Cron.taskFinished',
        ];

        foreach ($eventNames as $eventName) {
            EventManager::instance()->on($eventName, function (EventInterface $event): void {
                $this->recordFromEvent($event);
            });
        }
    }

    /**
     * Record a scheduled task from a Cake event.
     *
     * @param \Cake\Event\EventInterface<object> $event Scheduling finished event.
     * @return void
     */
    public function recordFromEvent(EventInterface $event): void
    {
        $task = $event->getData('event');
        if (is_object($task)) {
            if (!empty($task->skippedBecauseOverlapping)) {
                return;
            }

            $this->record($this->extractFromScheduledEvent($task));

            return;
        }

        $this->record((array)$event->getData());
    }

    /**
     * Record a scheduled task entry.
     *
     * @param array<string, mixed> $data Event payload.
     * @return void
     */
    public function record(array $data): void
    {
        if (!Speculum::isRecording()) {
            return;
        }

        Speculum::recordEntry(EntryType::ScheduledTask, IncomingEntry::make([
            'command' => $data['command'] ?? $data['description'] ?? null,
            'description' => $data['description'] ?? null,
            'expression' => $data['expression'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'user' => $data['user'] ?? null,
            'output' => $data['output'] ?? '',
            'exit_code' => $data['exit_code'] ?? $data['exitCode'] ?? null,
        ]));
    }

    /**
     * Extract Speculum payload fields from a Scheduling event object.
     *
     * @param object $task Crustum Scheduling Event / CallbackEvent.
     * @return array<string, mixed>
     */
    protected function extractFromScheduledEvent(object $task): array
    {
        $callbackEventClass = 'Crustum\\Scheduling\\CallbackEvent';
        $isCallback = class_exists($callbackEventClass) && $task instanceof $callbackEventClass;

        $command = $isCallback ? 'Closure' : ($task->command ?? null);
        if ($command === null && method_exists($task, 'getSummaryForDisplay')) {
            $command = (string)$task->getSummaryForDisplay();
        }

        $description = null;
        if (method_exists($task, 'getDescription')) {
            $description = $task->getDescription();
        }

        $timezone = $task->timezone ?? null;
        if ($timezone instanceof DateTimeZone) {
            $timezone = $timezone->getName();
        }

        $exitCode = $task->exitCode ?? null;
        if ($exitCode === null && isset($task->exit_code)) {
            $exitCode = $task->exit_code;
        }

        return [
            'command' => $command,
            'description' => $description,
            'expression' => $task->expression ?? null,
            'timezone' => $timezone,
            'user' => $task->user ?? null,
            'output' => $this->getEventOutput($task),
            'exit_code' => $exitCode,
        ];
    }

    /**
     * Read scheduled task output from the event's output file when available.
     *
     * @param object $task Scheduled event.
     * @return string
     */
    protected function getEventOutput(object $task): string
    {
        $output = $task->output ?? null;
        if (!is_string($output) || $output === '') {
            return '';
        }

        if (method_exists($task, 'getDefaultOutput') && $output === $task->getDefaultOutput()) {
            return '';
        }

        if (!empty($task->shouldAppendOutput)) {
            return '';
        }

        if (!is_file($output)) {
            return '';
        }

        $contents = file_get_contents($output);

        return is_string($contents) ? trim($contents) : '';
    }
}
