<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\Utility\Text;
use Crustum\Broadcasting\BroadcastingPlugin;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;

/**
 * Soft watcher for Crustum Broadcasting before/after send events.
 */
class BroadcastWatcher extends Watcher
{
    /**
     * Tag prefix for correlation lookups (indexed tags table).
     *
     * @var string
     */
    public const CORRELATION_TAG_PREFIX = 'correlation:';

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        if (!WatcherRegistry::isSoftAvailable(SoftFeature::Broadcasting)) {
            return;
        }

        EventManager::instance()->on(BroadcastingPlugin::EVENT_BEFORE_SEND, function (EventInterface $event): void {
            $this->beforeSend($event);
        });

        EventManager::instance()->on(BroadcastingPlugin::EVENT_SENT, function (EventInterface $event): void {
            $this->recordFromEvent($event);
        });
    }

    /**
     * Attach `__crustum.correlation_id` when Speculum is recording.
     *
     * @param \Cake\Event\EventInterface<object> $event Before-send event.
     * @return void
     */
    public function beforeSend(EventInterface $event): void
    {
        if (!Speculum::isRecording()) {
            return;
        }

        $payload = $event->getData('payload');
        if (!is_array($payload)) {
            return;
        }

        $existing = $payload[BroadcastingPlugin::RESERVED_META_KEY] ?? [];
        if (!is_array($existing)) {
            $existing = [];
        }

        if (!empty($existing['correlation_id']) && is_string($existing['correlation_id'])) {
            $event->setResult($payload);

            return;
        }

        $payload[BroadcastingPlugin::RESERVED_META_KEY] = array_merge($existing, [
            'correlation_id' => Text::uuid(),
            'origin_type' => PHP_SAPI === 'cli' ? EntryType::Command->value : EntryType::Request->value,
        ]);

        $event->setResult($payload);
    }

    /**
     * Record a broadcast from a Cake event.
     *
     * @param \Cake\Event\EventInterface<object> $event Broadcast sent event.
     * @return void
     */
    public function recordFromEvent(EventInterface $event): void
    {
        $this->record([
            'channels' => $event->getData('channels'),
            'event' => $event->getData('event') ?? $event->getData('eventName'),
            'payload' => $event->getData('payload') ?? $event->getData('data'),
            'connection' => $event->getData('connection') ?? $event->getData('connectionName'),
            'queued' => $event->getData('queued'),
        ]);
    }

    /**
     * Record a broadcast entry from normalized event data.
     *
     * @param array<string, mixed> $data Event data.
     * @return void
     */
    public function record(array $data): void
    {
        if (!Speculum::isRecording() || !WatcherRegistry::isSoftAvailable(SoftFeature::Broadcasting)) {
            return;
        }

        $channels = $data['channels'] ?? [];
        if (is_string($channels)) {
            $channels = [$channels];
        }

        if (!is_array($channels)) {
            $channels = [];
        }

        $channels = array_values(array_map(strval(...), $channels));

        $eventName = $data['event'] ?? null;
        if (!is_string($eventName) || $eventName === '') {
            return;
        }

        $connection = $data['connection'] ?? 'default';
        $payload = $data['payload'] ?? [];
        if (!is_array($payload)) {
            $payload = ['value' => $payload];
        }

        $correlationId = $this->extractCorrelationId($payload);
        $displayPayload = $payload;
        unset($displayPayload[BroadcastingPlugin::RESERVED_META_KEY]);

        $content = [
            'channels' => $channels,
            'event' => $eventName,
            'payload' => $displayPayload,
            'connection' => is_string($connection) ? $connection : 'default',
            'queued' => (bool)($data['queued'] ?? false),
        ];

        if ($correlationId !== null) {
            $content['correlation_id'] = $correlationId;
        }

        $entry = IncomingEntry::make($content);

        $tags = $channels;
        if ($correlationId !== null) {
            $tags[] = self::CORRELATION_TAG_PREFIX . $correlationId;
        }

        if ($tags !== []) {
            $entry->tags(array_values(array_unique($tags)));
        }

        Speculum::recordEntry(EntryType::Broadcast, $entry);
    }

    /**
     * Read a correlation id from reserved broadcasting meta on the payload.
     *
     * @param array<string, mixed> $payload Payload.
     * @return string|null
     */
    protected function extractCorrelationId(array $payload): ?string
    {
        $meta = $payload[BroadcastingPlugin::RESERVED_META_KEY] ?? null;
        if (!is_array($meta)) {
            return null;
        }

        $id = $meta['correlation_id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }
}
