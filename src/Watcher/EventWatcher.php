<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Core\Exception\CakeException;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Event\RecordingEventManager;
use Crustum\Speculum\Speculum;

/**
 * Records application events with framework noise filtering.
 *
 * CakePHP has no native wildcard listener. Exact names use EventManager::on();
 * masks with `*`, `?`, or `[` use RecordingEventManager (for example `Model.*`, `*`).
 * You may also call recordEvent() from application code.
 */
class EventWatcher extends Watcher
{
    /**
     * @inheritDoc
     */
    public function register(): void
    {
        $exact = [];
        $masks = [];

        foreach ($this->options['events'] ?? [] as $eventName) {
            $eventName = (string)$eventName;
            if ($this->isMask($eventName)) {
                $masks[] = $eventName;
            } else {
                $exact[] = $eventName;
            }
        }

        foreach ($exact as $eventName) {
            EventManager::instance()->on($eventName, function (EventInterface $event): void {
                $this->recordEvent($event->getName(), $this->payloadFromEvent($event));
            });
        }

        if ($masks !== []) {
            RecordingEventManager::install($this, $masks);
        }
    }

    /**
     * Build a recordable payload from a Cake event instance.
     *
     * @param \Cake\Event\EventInterface<object> $event Dispatched event.
     * @return array{0: object|null, 1: array<string, mixed>}
     */
    public function payloadFromEvent(EventInterface $event): array
    {
        $subject = null;
        try {
            $subject = $event->getSubject();
        } catch (CakeException) {
        }

        return [$subject, $event->getData()];
    }

    /**
     * Record an application event entry.
     *
     * @param string $eventName Event name.
     * @param array<int, mixed> $payload Payload.
     * @return void
     */
    public function recordEvent(string $eventName, array $payload = []): void
    {
        if (!Speculum::isRecording() || $this->shouldIgnore($eventName)) {
            return;
        }

        $broadcast = false;
        if (class_exists($eventName)) {
            $interfaces = class_implements($eventName) ?: [];
            $broadcast = isset($interfaces['Crustum\\Broadcasting\\ShouldBroadcast'])
                || isset($interfaces['Cake\\Broadcasting\\ShouldBroadcast']);
        }

        Speculum::recordEntry(EntryType::Event, IncomingEntry::make([
            'name' => $eventName,
            'payload' => $this->extractPayload($payload),
            'broadcast' => $broadcast,
        ]));
    }

    /**
     * Whether the configured event string is an fnmatch mask.
     *
     * @param string $eventName Event name or mask.
     * @return bool
     */
    protected function isMask(string $eventName): bool
    {
        return str_contains($eventName, '*')
            || str_contains($eventName, '?')
            || str_contains($eventName, '[');
    }

    /**
     * Extract a serializable payload from event arguments.
     *
     * @param array<int, mixed> $payload Payload.
     * @return array<int, mixed>|null
     */
    protected function extractPayload(array $payload): ?array
    {
        $formatted = [];
        foreach ($payload as $value) {
            if (is_object($value)) {
                $formatted[] = [
                    'class' => $value::class,
                    'properties' => method_exists($value, 'toArray') ? $value->toArray() : [],
                ];
            } else {
                $formatted[] = $value;
            }
        }

        return $formatted !== [] ? $formatted : null;
    }

    /**
     * Determine whether the event name should be ignored.
     *
     * @param string $eventName Event name.
     * @return bool
     */
    protected function shouldIgnore(string $eventName): bool
    {
        if ($this->eventIsIgnored($eventName)) {
            return true;
        }

        return Speculum::$ignoreFrameworkEvents && $this->eventIsFiredByTheFramework($eventName);
    }

    /**
     * Determine whether the event is a framework-fired event.
     *
     * @param string $eventName Event name.
     * @return bool
     */
    protected function eventIsFiredByTheFramework(string $eventName): bool
    {
        $patterns = [
            'Cake\\*',
            'Controller.*',
            'Model.*',
            'View.*',
            'Connection.*',
            'Server.*',
            'Application.*',
            'Command.*',
            'Console.*',
            'Middleware.*',
            'Error.*',
            'Exception.*',
            'Log.*',
            'Mailer.*',
            'Email.*',
            'HttpClient.*',
            'Http.Client.*',
            'Cache.*',
            'Processor.*',
            'Queue.*',
            'Auth.*',
            'Form.*',
            'Cell.*',
            'Paginator.*',
            'Crustum\\Speculum\\*',
        ];

        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $eventName)) {
                return true;
            }
        }

        return str_starts_with($eventName, 'Cake\\')
            || str_starts_with($eventName, 'Crustum\\Speculum\\');
    }

    /**
     * Determine whether the event matches a configured ignore pattern.
     *
     * @param string $eventName Event name.
     * @return bool
     */
    protected function eventIsIgnored(string $eventName): bool
    {
        foreach ($this->options['ignore'] ?? [] as $pattern) {
            if (fnmatch((string)$pattern, $eventName)) {
                return true;
            }
        }

        return false;
    }
}
