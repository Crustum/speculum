<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Crustum\BlazeCast\Support\CrustumMeta;
use Crustum\BlazeCast\WebSocket\Event\AfterClientSendEvent;
use Crustum\BlazeCast\WebSocket\Event\MessageReceivedEvent;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use JsonException;

/**
 * Soft watcher for BlazeCast outbound deliveries and inbound client messages.
 */
class BlazeCastWatcher extends Watcher
{
    /**
     * Pusher / BlazeCast system event name prefixes and exact names to skip.
     *
     * @var list<string>
     */
    protected array $systemEvents = [
        'pusher:ping',
        'pusher:pong',
        'pusher:error',
        'pusher:subscribe',
        'pusher:unsubscribe',
        'pusher_internal:',
    ];

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        if (!WatcherRegistry::isSoftAvailable(SoftFeature::BlazeCast)) {
            return;
        }

        if ($this->recordsDeliveries()) {
            EventManager::instance()->on(AfterClientSendEvent::EVENT_NAME, function (EventInterface $event): void {
                if ($event instanceof AfterClientSendEvent) {
                    $this->recordDelivery($event);
                }
            });
        }

        if ($this->recordsMessages()) {
            EventManager::instance()->on(MessageReceivedEvent::EVENT_NAME, function (EventInterface $event): void {
                if ($event instanceof MessageReceivedEvent) {
                    $this->recordMessage($event);
                }
            });
        }
    }

    /**
     * Record fan-out delivery forensics.
     *
     * @param \Crustum\BlazeCast\WebSocket\Event\AfterClientSendEvent $event Event.
     * @return void
     */
    public function recordDelivery(AfterClientSendEvent $event): void
    {
        if (!Speculum::isRecording()) {
            return;
        }

        $enriched = $event->getEnrichedPayload();
        $meta = $enriched[CrustumMeta::KEY] ?? [];
        $correlationId = is_array($meta) && isset($meta['correlation_id']) && is_string($meta['correlation_id'])
            ? $meta['correlation_id']
            : null;

        $channel = (string)$event->getData('channel');
        $eventName = (string)$event->getData('event');
        $appId = (string)$event->getData('app_id');

        $content = [
            'direction' => 'out',
            'app_id' => $appId,
            'channel' => $channel,
            'event' => $eventName,
            'delivered_to' => $event->getDeliveredTo(),
            'connection_ids' => $event->getConnectionIds(),
            'payload' => $event->getData('payload'),
        ];

        if ($correlationId !== null) {
            $content['correlation_id'] = $correlationId;
        }

        $entry = IncomingEntry::make($content);
        $tags = [];
        if ($channel !== '') {
            $tags[] = $channel;
        }

        if ($correlationId !== null) {
            $tags[] = BroadcastWatcher::CORRELATION_TAG_PREFIX . $correlationId;
        }

        if ($tags !== []) {
            $entry->tags($tags);
        }

        Speculum::recordEntry(EntryType::BlazeCastDelivery, $entry);
    }

    /**
     * Record an inbound non-system client frame.
     *
     * @param \Crustum\BlazeCast\WebSocket\Event\MessageReceivedEvent $event Event.
     * @return void
     */
    public function recordMessage(MessageReceivedEvent $event): void
    {
        if (!Speculum::isRecording()) {
            return;
        }

        $raw = $event->getData('data');
        if (!is_string($raw) || $raw === '') {
            return;
        }

        try {
            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $decoded = null;
        }

        if (!is_array($decoded)) {
            return;
        }

        $eventName = $decoded['event'] ?? null;
        if (!is_string($eventName) || $eventName === '' || $this->isSystemEvent($eventName)) {
            return;
        }

        $connection = $event->getConnection();
        $connectionId = $connection->getId();
        $channel = $decoded['channel'] ?? null;

        $payload = $decoded['data'] ?? null;
        if (is_string($payload)) {
            try {
                $payload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
            }
        }

        $entry = IncomingEntry::make([
            'direction' => 'in',
            'connection_id' => $connectionId,
            'event' => $eventName,
            'channel' => is_string($channel) ? $channel : null,
            'payload' => $payload,
        ]);

        $tags = [$connectionId];
        if (is_string($channel) && $channel !== '') {
            $tags[] = $channel;
        }

        $entry->tags($tags);

        Speculum::recordEntry(EntryType::BlazeCastMessage, $entry);
    }

    /**
     * Whether delivery entries should be recorded.
     *
     * @return bool
     */
    protected function recordsDeliveries(): bool
    {
        return (bool)($this->options['deliveries'] ?? true);
    }

    /**
     * Whether message entries should be recorded.
     *
     * @return bool
     */
    protected function recordsMessages(): bool
    {
        return (bool)($this->options['messages'] ?? true);
    }

    /**
     * Whether the event name is a BlazeCast system/control event.
     *
     * @param string $eventName Event name.
     * @return bool
     */
    protected function isSystemEvent(string $eventName): bool
    {
        return array_any(
            $this->systemEvents,
            fn($system): bool => $eventName === $system || str_starts_with($eventName, (string)$system),
        );
    }
}
