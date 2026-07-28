<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Cache\Event\CacheAfterDeleteEvent;
use Cake\Cache\Event\CacheAfterGetEvent;
use Cake\Cache\Event\CacheAfterSetEvent;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Sanitizer\AbstractPatternSanitizer;
use Crustum\Speculum\Speculum;

/**
 * Records cache hits, misses, writes, and deletes when Cache events fire.
 */
class CacheWatcher extends Watcher
{
    /**
     * Default ignore patterns for CakePHP framework cache keys (not third-party).
     *
     * @var list<string>
     */
    protected const DEFAULT_IGNORE = [
        '*_cake_core_*',
        '*_cake_model_*',
        '*_cake_translations*',
        '*_cake_routes_*',
        'cake_core_*',
        'cake_model_*',
        'cake_translations*',
        'cake_routes_*',
        'session_*',
    ];

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        EventManager::instance()->on(CacheAfterGetEvent::NAME, function (CacheAfterGetEvent $event): void {
            $this->recordTyped(
                $event->getResult() === true ? 'hit' : 'miss',
                $event->getKey(),
                $event->getValue(),
            );
        });

        EventManager::instance()->on(CacheAfterSetEvent::NAME, function (CacheAfterSetEvent $event): void {
            $this->recordTyped(
                'set',
                $event->getKey(),
                $event->getValue(),
                $event->getTtl(),
            );
        });

        EventManager::instance()->on(CacheAfterDeleteEvent::NAME, function (CacheAfterDeleteEvent $event): void {
            $this->recordTyped('forget', $event->getKey());
        });
    }

    /**
     * Record a typed cache operation entry.
     *
     * @param string $type Operation type.
     * @param string $key Cache key.
     * @param mixed $value Value.
     * @param mixed $expiration Expiration / TTL.
     * @return void
     */
    public function recordTyped(string $type, string $key, mixed $value = null, mixed $expiration = null): void
    {
        if (!Speculum::isRecording()) {
            return;
        }

        $key = rawurldecode($key);

        if ($this->shouldIgnore($key)) {
            return;
        }

        if (in_array($key, $this->options['hidden'] ?? [], true)) {
            $value = AbstractPatternSanitizer::DEFAULT_REPLACEMENT;
        }

        Speculum::recordEntry(EntryType::Cache, IncomingEntry::make([
            'type' => $type,
            'key' => $key,
            'value' => $value,
            'expiration' => $expiration,
        ]));
    }

    /**
     * Record a cache operation from a loosely typed event payload.
     *
     * @param string $event Event name.
     * @param array<string, mixed> $data Event data.
     * @return void
     */
    public function record(string $event, array $data): void
    {
        $key = (string)($data['key'] ?? '');
        $value = $data['value'] ?? $data['result'] ?? null;
        $expiration = $data['duration'] ?? $data['expiration'] ?? $data['ttl'] ?? null;

        $type = match (true) {
            str_contains(strtolower($event), 'hit') => 'hit',
            str_contains(strtolower($event), 'miss') => 'miss',
            str_contains(strtolower($event), 'get') => isset($data['success']) && $data['success'] === false ? 'miss' : 'hit',
            str_contains(strtolower($event), 'set') || str_contains(strtolower($event), 'write') => 'set',
            str_contains(strtolower($event), 'delete') => 'forget',
            default => 'hit',
        };

        $this->recordTyped($type, $key, $value, $expiration);
    }

    /**
     * Manually record a cache operation (for engines without events).
     *
     * @param string $type Operation type.
     * @param string $key Cache key.
     * @param mixed $value Value.
     * @param mixed $expiration Expiration.
     * @return void
     */
    public function recordManual(string $type, string $key, mixed $value = null, mixed $expiration = null): void
    {
        $this->recordTyped($type, $key, $value, $expiration);
    }

    /**
     * Record a cache operation from a Cake Cache event object.
     *
     * @param \Cake\Event\EventInterface<object> $event Cake cache event.
     * @return void
     */
    public function recordFromEvent(EventInterface $event): void
    {
        if ($event instanceof CacheAfterGetEvent) {
            $this->recordTyped(
                $event->getResult() === true ? 'hit' : 'miss',
                $event->getKey(),
                $event->getValue(),
            );

            return;
        }

        if ($event instanceof CacheAfterSetEvent) {
            $this->recordTyped('set', $event->getKey(), $event->getValue(), $event->getTtl());

            return;
        }

        if ($event instanceof CacheAfterDeleteEvent) {
            $this->recordTyped('forget', $event->getKey());
        }
    }

    /**
     * Determine whether the cache key should be ignored.
     *
     * @param string $key Cache key.
     * @return bool
     */
    protected function shouldIgnore(string $key): bool
    {
        if ($key === '') {
            return true;
        }

        $normalized = strtolower($key);
        if (
            str_starts_with($normalized, 'speculum')
            || str_contains($normalized, 'speculum:pause-recording')
            || str_contains($normalized, 'speculum%3apause-recording')
            || str_ends_with($normalized, 'pause-recording')
        ) {
            return true;
        }

        return array_any($this->ignorePatterns(), fn(string $pattern): bool => fnmatch($pattern, $key));
    }

    /**
     * Return ignore patterns for cache keys.
     *
     * @return list<string>
     */
    protected function ignorePatterns(): array
    {
        $configured = array_values(array_map(strval(...), $this->options['ignore'] ?? []));

        if (($this->options['ignore_framework'] ?? true) === false) {
            return $configured;
        }

        return array_values(array_unique(array_merge(self::DEFAULT_IGNORE, $configured)));
    }
}
