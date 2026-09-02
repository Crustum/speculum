<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\ORM\Table;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;

/**
 * Soft watcher for Crustum Explorator searches and index writes.
 *
 * Listens to `Explorator.SearchPerformed` and `Explorator.IndexWritePerformed`.
 * Registers only when `crustum/explorator` is loaded. Does not require Explorator classes at compile time.
 */
class SearchesWatcher extends Watcher
{
    /**
     * Event name fired by crustum/explorator after a search completes.
     */
    public const EVENT_NAME = 'Explorator.SearchPerformed';

    /**
     * Event name fired by crustum/explorator after an index write completes.
     */
    public const WRITE_EVENT_NAME = 'Explorator.IndexWritePerformed';

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        if (!WatcherRegistry::isSoftAvailable(SoftFeature::Explorator)) {
            return;
        }

        EventManager::instance()->on(self::EVENT_NAME, function (EventInterface $event): void {
            $this->recordFromEvent($event);
        });

        EventManager::instance()->on(self::WRITE_EVENT_NAME, function (EventInterface $event): void {
            $this->recordFromWriteEvent($event);
        });
    }

    /**
     * Record a search from a Cake event.
     *
     * @param \Cake\Event\EventInterface<object> $event Explorator search event.
     * @return void
     */
    public function recordFromEvent(EventInterface $event): void
    {
        $data = $event->getData();
        if (!is_array($data)) {
            $data = [];
        }

        $this->record([
            'query' => $data['query'] ?? '',
            'index' => $data['index'] ?? null,
            'engine' => $data['engine'] ?? '',
            'hits' => $data['hits'] ?? null,
            'count' => null,
            'duration_ms' => $data['duration_ms'] ?? 0.0,
            'operation' => $data['operation'] ?? 'search',
            'page' => $data['page'] ?? null,
            'per_page' => $data['per_page'] ?? null,
            'table' => $this->tableNameFromSubject($event->getSubject()),
            'request' => $data['request'] ?? null,
            'response' => $data['response'] ?? null,
        ]);
    }

    /**
     * Record an index write from a Cake event.
     *
     * @param \Cake\Event\EventInterface<object> $event Explorator index write event.
     * @return void
     */
    public function recordFromWriteEvent(EventInterface $event): void
    {
        $data = $event->getData();
        if (!is_array($data)) {
            $data = [];
        }

        $count = is_int($data['count'] ?? null) ? $data['count'] : (int)($data['count'] ?? 0);
        $operation = is_string($data['operation'] ?? null) ? $data['operation'] : 'update';
        $index = is_string($data['index'] ?? null) ? $data['index'] : null;
        $table = $this->tableNameFromSubject($event->getSubject());
        $label = $index ?? $table ?? '';

        $this->record([
            'query' => trim($operation . ' · ' . $label),
            'index' => $index,
            'engine' => $data['engine'] ?? '',
            'hits' => null,
            'count' => $count,
            'duration_ms' => $data['duration_ms'] ?? 0.0,
            'operation' => $operation,
            'page' => null,
            'per_page' => null,
            'table' => $table,
            'request' => $data['request'] ?? null,
            'response' => $data['response'] ?? null,
        ]);
    }

    /**
     * Record a search or index-write entry from normalized event data.
     *
     * @param array{
     *     query?: mixed,
     *     index?: mixed,
     *     engine?: mixed,
     *     hits?: mixed,
     *     count?: mixed,
     *     duration_ms?: mixed,
     *     operation?: mixed,
     *     page?: mixed,
     *     per_page?: mixed,
     *     table?: mixed,
     *     request?: mixed,
     *     response?: mixed
     * } $data Event data.
     * @return void
     */
    public function record(array $data): void
    {
        if (!Speculum::isRecording() || !WatcherRegistry::isSoftAvailable(SoftFeature::Explorator)) {
            return;
        }

        $query = is_string($data['query'] ?? null) ? $data['query'] : (string)($data['query'] ?? '');
        $engine = is_string($data['engine'] ?? null) ? $data['engine'] : (string)($data['engine'] ?? '');
        $index = is_string($data['index'] ?? null) ? $data['index'] : null;
        $table = is_string($data['table'] ?? null) ? $data['table'] : null;
        $operation = is_string($data['operation'] ?? null) ? $data['operation'] : 'search';
        $hits = is_int($data['hits'] ?? null) ? $data['hits'] : null;
        $count = is_int($data['count'] ?? null) ? $data['count'] : null;
        $page = is_int($data['page'] ?? null) ? $data['page'] : null;
        $perPage = is_int($data['per_page'] ?? null) ? $data['per_page'] : null;
        $durationMs = (float)($data['duration_ms'] ?? 0.0);
        $slow = $this->isSlowDuration($durationMs);
        $isWrite = $operation === 'update' || $operation === 'delete';
        $family = $isWrite
            ? null
            : md5($engine . '|' . ($index ?? '') . '|' . $operation . '|' . $query);

        $content = [
            'query' => $query,
            'hits' => $hits,
            'count' => $count,
            'duration_ms' => $durationMs,
            'time' => number_format($durationMs, 2, '.', ''),
            'slow' => $slow,
            'engine' => $engine,
            'index' => $index,
            'table' => $table,
            'operation' => $operation,
            'page' => $page,
            'per_page' => $perPage,
            'hash' => $family,
        ];

        if ($this->shouldLogPayload('request') && is_array($data['request'] ?? null)) {
            $content['request'] = $data['request'];
        }

        if ($this->shouldLogPayload('response') && is_array($data['response'] ?? null)) {
            $content['response'] = $data['response'];
        }

        Speculum::recordEntry(
            EntryType::Explorator,
            IncomingEntry::make($content)->tags($this->slowTags($durationMs))->withFamilyHash($family),
        );
    }

    /**
     * Whether request or response payloads should be stored (default true).
     *
     * @param string $key Option key (`request` or `response`).
     * @return bool
     */
    protected function shouldLogPayload(string $key): bool
    {
        if (!array_key_exists($key, $this->options)) {
            return true;
        }

        return (bool)$this->options[$key];
    }

    /**
     * Resolve a table name from an event subject.
     *
     * @param mixed $subject Event subject.
     * @return string|null
     */
    protected function tableNameFromSubject(mixed $subject): ?string
    {
        if ($subject instanceof Table) {
            return $subject->getTable();
        }

        if (is_object($subject) && method_exists($subject, 'getTable')) {
            $resolved = $subject->getTable();

            return is_string($resolved) ? $resolved : null;
        }

        return null;
    }
}
