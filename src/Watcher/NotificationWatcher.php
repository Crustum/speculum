<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Crustum\Notification\AnonymousNotifiable;
use Crustum\Notification\ShouldQueueInterface;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use ReflectionObject;

/**
 * Soft watcher for Crustum Notification (`Model.Notification.sent`).
 *
 * Registers only when `crustum/notification` is present (see Speculum soft deps).
 */
class NotificationWatcher extends Watcher
{
    /**
     * @inheritDoc
     */
    public function register(): void
    {
        if (!WatcherRegistry::isSoftAvailable(SoftFeature::Notification)) {
            return;
        }

        EventManager::instance()->on('Model.Notification.sent', function (EventInterface $event): void {
            $this->recordFromEvent($event);
        });
    }

    /**
     * Record a notification from a Cake event.
     *
     * @param \Cake\Event\EventInterface<object> $event Notification sent event.
     * @return void
     */
    public function recordFromEvent(EventInterface $event): void
    {
        $this->record([
            'notifiable' => $event->getData('notifiable'),
            'notification' => $event->getData('notification') ?? $event->getData('class'),
            'channel' => $event->getData('channel'),
            'response' => $event->getData('response'),
            'queued' => $event->getData('queued'),
        ]);
    }

    /**
     * Record a notification entry from normalized event data.
     *
     * @param array<string, mixed> $data Event data.
     * @return void
     */
    public function record(array $data): void
    {
        if (!Speculum::isRecording() || !WatcherRegistry::isSoftAvailable(SoftFeature::Notification)) {
            return;
        }

        $notification = $data['notification'] ?? null;
        $notifiable = $data['notifiable'] ?? null;
        $channel = $data['channel'] ?? null;

        if ($notification === null && $channel === null) {
            return;
        }

        $queued = $data['queued'] ?? null;
        if ($queued === null && is_object($notification)) {
            $queued = $notification instanceof ShouldQueueInterface
                || in_array(ShouldQueueInterface::class, class_implements($notification), true);
        }

        $notifiableLabel = $this->formatNotifiable($notifiable);
        $entry = IncomingEntry::make([
            'notification' => $this->formatNotification($notification),
            'queued' => (bool)$queued,
            'notifiable' => $notifiableLabel,
            'channel' => is_string($channel) ? $channel : null,
            'response' => $this->formatResponse($data['response'] ?? null),
        ]);

        if (is_string($notifiableLabel) && $notifiableLabel !== '') {
            $entry->tags([$notifiableLabel]);
        }

        Speculum::recordEntry(EntryType::Notification, $entry);
    }

    /**
     * Format a notification class name for storage.
     *
     * @param mixed $notification Notification instance or class string.
     * @return string|null
     */
    protected function formatNotification(mixed $notification): ?string
    {
        if (is_string($notification) && $notification !== '') {
            return $notification;
        }

        if (is_object($notification)) {
            return $this->classLabel($notification);
        }

        return null;
    }

    /**
     * Return a JSON-safe label for an object's class.
     *
     * Anonymous classes encode a NUL byte in their `::class` name, which
     * Postgres `jsonb` rejects. The byte is stripped so the stored value stays
     * a valid JSON string across every supported driver.
     *
     * @param object $object Object to label.
     * @return string
     */
    protected function classLabel(object $object): string
    {
        return str_replace("\0", '', $object::class);
    }

    /**
     * Format a notifiable label for storage and tagging.
     *
     * @param mixed $notifiable Notifiable entity or anonymous route bag.
     * @return string|null
     */
    protected function formatNotifiable(mixed $notifiable): ?string
    {
        if ($notifiable instanceof EntityInterface) {
            $id = $notifiable->get('id');
            if ($id === null && $notifiable->has('uuid')) {
                $id = $notifiable->get('uuid');
            }

            return $notifiable::class . ':' . $id;
        }

        if (class_exists(AnonymousNotifiable::class) && $notifiable instanceof AnonymousNotifiable) {
            return 'Anonymous:' . implode(',', $this->anonymousRoutes($notifiable));
        }

        if (is_string($notifiable) && $notifiable !== '') {
            return $notifiable;
        }

        if (is_object($notifiable)) {
            return $this->classLabel($notifiable);
        }

        return null;
    }

    /**
     * Extract route values from an anonymous notifiable.
     *
     * @param \Crustum\Notification\AnonymousNotifiable $notifiable Anonymous notifiable.
     * @return list<string>
     */
    protected function anonymousRoutes(AnonymousNotifiable $notifiable): array
    {
        $routes = [];
        $reflection = new ReflectionObject($notifiable);
        if ($reflection->hasProperty('routes')) {
            $property = $reflection->getProperty('routes');
            $map = $property->getValue($notifiable);
            if (is_array($map)) {
                foreach ($map as $route) {
                    $routes[] = is_array($route) ? implode(',', array_map(strval(...), $route)) : (string)$route;
                }
            }
        }

        return $routes;
    }

    /**
     * Normalize a channel response for storage.
     *
     * @param mixed $response Channel response.
     * @return mixed
     */
    protected function formatResponse(mixed $response): mixed
    {
        if ($response === null || is_scalar($response) || is_array($response)) {
            return $response;
        }

        if ($response instanceof EntityInterface) {
            return [
                'class' => $response::class,
                'id' => $response->get('id'),
            ];
        }

        if (is_object($response)) {
            return $this->classLabel($response);
        }

        return null;
    }
}
