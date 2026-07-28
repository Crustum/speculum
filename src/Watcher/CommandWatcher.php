<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Console\Arguments;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Speculum;
use WeakMap;

/**
 * Records Cake console command executions.
 */
class CommandWatcher extends Watcher
{
    /**
     * In-flight command subjects keyed for duration timing.
     *
     * @var \WeakMap<object, float>
     */
    protected WeakMap $startedAt;

    /**
     * Create the watcher and initialize duration tracking.
     *
     * @param array<string, mixed> $options Watcher options.
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        $this->startedAt = new WeakMap();
    }

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        EventManager::instance()->on('Command.beforeExecute', function (EventInterface $event): void {
            $this->markStartedFromEvent($event);
        });

        EventManager::instance()->on('Command.afterExecute', function (EventInterface $event): void {
            $this->recordFromEvent($event);
        });

        EventManager::instance()->on('Console.afterExecute', function (EventInterface $event): void {
            $this->recordFromEvent($event);
        });
    }

    /**
     * Mark command execution start for duration measurement.
     *
     * @param \Cake\Event\EventInterface<object> $event Command.beforeExecute event.
     * @return void
     */
    public function markStartedFromEvent(EventInterface $event): void
    {
        $this->startedAt[$event->getSubject()] = microtime(true);
    }

    /**
     * Record a command execution from a Cake event.
     *
     * @param \Cake\Event\EventInterface<object> $event Command.afterExecute event.
     * @return void
     */
    public function recordFromEvent(EventInterface $event): void
    {
        $command = $event->getSubject();
        $commandName = $command::class;
        $args = $event->getData('args');
        $result = $event->getData('result');

        $arguments = [];
        $options = [];
        if ($args instanceof Arguments) {
            $arguments = $args->getArguments();
            $options = $args->getOptions();
        } elseif (is_array($args)) {
            $arguments = $args;
        }

        $exitCode = 0;
        if (is_int($result)) {
            $exitCode = $result;
        } elseif (is_numeric($result)) {
            $exitCode = (int)$result;
        }

        $duration = null;
        if (isset($this->startedAt[$command])) {
            $duration = (int)round((microtime(true) - $this->startedAt[$command]) * 1000);
            unset($this->startedAt[$command]);
        }

        $this->record($commandName, $arguments, $exitCode, $options, $duration);
    }

    /**
     * Record a console command entry.
     *
     * @param string $command Command class/name.
     * @param array<int|string, mixed> $arguments Arguments.
     * @param int $exitCode Exit code.
     * @param array<string, mixed> $options Options.
     * @param int|null $duration Duration in milliseconds.
     * @return void
     */
    public function record(
        string $command,
        array $arguments = [],
        int $exitCode = 0,
        array $options = [],
        ?int $duration = null,
    ): void {
        if (!Speculum::isRecording() || $this->shouldIgnore($command)) {
            return;
        }

        $content = [
            'command' => $command,
            'exit_code' => $exitCode,
            'arguments' => $arguments,
            'options' => $options,
        ];
        [$content, $tags] = $this->withMeasuredDuration($content, $duration);

        Speculum::recordEntry(
            EntryType::Command,
            IncomingEntry::make($content)->tags($tags),
        );
    }

    /**
     * Determine whether the command matches an ignore pattern.
     *
     * @param string $command Command name.
     * @return bool
     */
    protected function shouldIgnore(string $command): bool
    {
        foreach ($this->options['ignore'] ?? [] as $pattern) {
            if (fnmatch((string)$pattern, $command)) {
                return true;
            }
        }

        return false;
    }
}
