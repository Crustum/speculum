<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Inspection;

use Cake\Database\Exception\DatabaseException;
use Cake\Utility\Inflector;
use Crustum\Speculum\Contract\EntriesRepository;
use Crustum\Speculum\Entry\EntryResult;
use Crustum\Speculum\Entry\Presentation\EntryPresentationInterface;
use Crustum\Speculum\Entry\Presentation\PresentationRegistry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Throwable;

/**
 * Shows one Speculum entry with full batch context for human and agent consumers.
 *
 * Returns plain data consumed by `speculum show` (detail tables or JSON passthrough)
 * and, later, MCP tools. Commands calling this service stay thin.
 */
final class EntryDetail
{
    /**
     * Create an entry detail service.
     *
     * @param \Crustum\Speculum\Contract\EntriesRepository $entries Entries repository.
     */
    public function __construct(
        private readonly EntriesRepository $entries,
    ) {
    }

    /**
     * Show an entry with batch context.
     *
     * @param string $id Entry UUID, short prefix, `latest`, or `latest:{type}`.
     * @param string|null $typeFilter Comma-separated batch type filter.
     * @param bool $full Whether to skip truncation.
     * @return array{entry: array<string,mixed>, createdAt: \DateTimeInterface, detail: array{label: string, subtitle: string, fields: array<string,string>, list: array{label: string, items: list<string>, more: int, moreLabel: string}|null, blocks: array<string,string>}, batchId: string|null, batch: list<array<string,mixed>>, batchGroups: array{queries: array{headers: list<string>, rows: list<list<string>>, stats: array{total: int, time: float, slow: int, duplicateGroups: int}, more: int}, exceptions: array{headers: list<string>, rows: list<list<string>>, total: int, more: int}, cache: array{stats: array{hits: int, misses: int, total: int, rate: float|null}, headers: list<string>, rows: list<list<string>>, total: int, more: int}, logs: array{headers: list<string>, rows: list<list<string>>, total: int, more: int}, others: list<array{label: string, count: int, items: list<array{id: string, summary: string}>, more: int}>}, requestedTypes: list<string>}
     * @throws \Crustum\Speculum\Entry\Inspection\EntryInspectionException
     */
    public function show(string $id, ?string $typeFilter = null, bool $full = false): array
    {
        $entry = $this->findEntry($id);
        $requestedTypes = $this->resolveRequestedTypes($typeFilter);

        $batchId = isset($entry->content['updated_batch_id'])
            && is_string($entry->content['updated_batch_id'])
            && $entry->content['updated_batch_id'] !== ''
            ? $entry->content['updated_batch_id']
            : $entry->batchId;

        $batch = $batchId !== '' ? $this->loadBatch($batchId, $entry, $requestedTypes) : [];

        return [
            'entry' => $entry->jsonSerialize(),
            'createdAt' => $entry->createdAt,
            'detail' => PresentationRegistry::for($entry->type)->detailFields($entry, $full),
            'batchId' => $batchId !== '' ? $batchId : null,
            'batch' => array_map(
                static fn(EntryResult $result): array => $result->jsonSerialize(),
                $batch,
            ),
            'batchGroups' => $this->groupBatch($batch, $full),
            'requestedTypes' => $requestedTypes,
        ];
    }

    /**
     * Find the entry for the given id shortcut or UUID.
     *
     * @param string $id Entry id.
     * @return \Crustum\Speculum\Entry\EntryResult
     * @throws \Crustum\Speculum\Entry\Inspection\EntryInspectionException
     */
    protected function findEntry(string $id): EntryResult
    {
        if ($id === 'latest' || str_starts_with($id, 'latest:')) {
            $part = $id === 'latest' ? '' : substr($id, strlen('latest:'));
            $type = $part === '' ? null : $this->resolveListType($part);

            $results = $this->entries->get($type, (new EntryQueryOptions())->limit(1));
            $entry = $results[0] ?? null;
            if (!$entry instanceof EntryResult) {
                throw new EntryInspectionException(
                    'No ' . ($part !== '' ? $part . ' ' : '') . 'entries found.',
                );
            }

            return $entry;
        }

        try {
            return $this->entries->find($id);
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new EntryInspectionException("Entry not found: {$id}");
        }
    }

    /**
     * Resolve a `latest:{type}` part to a repository type filter.
     *
     * @param string $part Type part.
     * @return list<string>|string
     * @throws \Crustum\Speculum\Entry\Inspection\EntryInspectionException
     */
    protected function resolveListType(string $part): string|array
    {
        $type = PresentationRegistry::resolveType($part);
        if ($type === null) {
            throw new EntryInspectionException(
                'Invalid entry type: ' . $part
                . "\nValid types: " . implode(', ', PresentationRegistry::validTypes()),
            );
        }

        return $type;
    }

    /**
     * Resolve the comma-separated batch type filter to type values.
     *
     * @param string|null $typeFilter Raw filter.
     * @return list<string>
     * @throws \Crustum\Speculum\Entry\Inspection\EntryInspectionException
     */
    protected function resolveRequestedTypes(?string $typeFilter): array
    {
        if ($typeFilter === null || $typeFilter === '') {
            return [];
        }

        $types = [];
        foreach (explode(',', $typeFilter) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $resolved = PresentationRegistry::resolveType($part);
            if ($resolved === null) {
                throw new EntryInspectionException(
                    'Invalid entry type: ' . $part
                    . "\nValid types: " . implode(', ', PresentationRegistry::validTypes()),
                );
            }

            foreach ((array)$resolved as $value) {
                if (!in_array($value, $types, true)) {
                    $types[] = $value;
                }
            }
        }

        return $types;
    }

    /**
     * Load chronological batch entries excluding the entry itself.
     *
     * @param string $batchId Batch UUID.
     * @param \Crustum\Speculum\Entry\EntryResult $entry Shown entry.
     * @param list<string> $requestedTypes Type filter.
     * @return list<\Crustum\Speculum\Entry\EntryResult>
     */
    protected function loadBatch(string $batchId, EntryResult $entry, array $requestedTypes): array
    {
        $options = EntryQueryOptions::forBatchId($batchId)->limit(EntryQueryOptions::UNLIMITED);
        /** @var list<\Crustum\Speculum\Entry\EntryResult> $results */
        $results = $this->entries->get(null, $options);
        $results = array_reverse($results);

        $batch = [];
        foreach ($results as $result) {
            if ((string)$result->id === (string)$entry->id) {
                continue;
            }

            if ($requestedTypes !== [] && !in_array($result->type, $requestedTypes, true)) {
                continue;
            }

            $batch[] = $result;
        }

        return $batch;
    }

    /**
     * Group batch entries into console-ready sections with plain data.
     *
     * @param list<\Crustum\Speculum\Entry\EntryResult> $batch Batch entries.
     * @param bool $full Whether to skip truncation.
     * @return array{queries: array{headers: list<string>, rows: list<list<string>>, stats: array{total: int, time: float, slow: int, duplicateGroups: int}, more: int}, exceptions: array{headers: list<string>, rows: list<list<string>>, total: int, more: int}, cache: array{stats: array{hits: int, misses: int, total: int, rate: float|null}, headers: list<string>, rows: list<list<string>>, total: int, more: int}, logs: array{headers: list<string>, rows: list<list<string>>, total: int, more: int}, others: list<array{label: string, count: int, items: list<array{id: string, summary: string}>, more: int}>}
     */
    protected function groupBatch(array $batch, bool $full = false): array
    {
        $byType = [];
        foreach ($batch as $result) {
            $byType[$result->type][] = $result;
        }

        $queries = $byType[EntryType::Query->value] ?? [];
        $exceptions = $byType[EntryType::Exception->value] ?? [];
        $caches = $byType[EntryType::Cache->value] ?? [];
        $logs = $byType[EntryType::Log->value] ?? [];

        $queryPresentation = PresentationRegistry::for(EntryType::Query->value);
        $queryFlags = $this->queryFlags($queries);

        $queryRows = [];
        foreach (array_slice($queries, 0, 20) as $position => $result) {
            $queryRows[] = $queryPresentation->batchRow(
                $result,
                $position + 1,
                $queryFlags[(string)$result->id] ?? [],
                $full,
            );
        }

        $exceptionPresentation = PresentationRegistry::for(EntryType::Exception->value);
        $cachePresentation = PresentationRegistry::for(EntryType::Cache->value);
        $logPresentation = PresentationRegistry::for(EntryType::Log->value);

        $others = [];
        foreach ($byType as $type => $results) {
            if (
                in_array($type, [
                    EntryType::Query->value,
                    EntryType::Exception->value,
                    EntryType::Cache->value,
                    EntryType::Log->value,
                ], true)
            ) {
                continue;
            }

            $items = [];
            foreach (array_slice($results, 0, 5) as $result) {
                $items[] = [
                    'id' => substr((string)$result->id, 0, 8),
                    'summary' => PresentationRegistry::for($result->type)->summarize($result, $full),
                ];
            }

            $others[] = [
                'label' => Inflector::pluralize(Inflector::humanize($type)),
                'count' => count($results),
                'items' => $items,
                'more' => max(0, count($results) - 5),
            ];
        }

        return [
            'queries' => [
                'headers' => $queryPresentation->batchHeaders(),
                'rows' => $queryRows,
                'stats' => $this->queryStats($queries),
                'more' => max(0, count($queries) - 20),
            ],
            'exceptions' => [
                'headers' => $exceptionPresentation->batchHeaders(),
                'rows' => $this->batchRows($exceptionPresentation, $exceptions, 10, $full),
                'total' => count($exceptions),
                'more' => max(0, count($exceptions) - 10),
            ],
            'cache' => [
                'stats' => $this->cacheStats($caches),
                'headers' => $cachePresentation->batchHeaders(),
                'rows' => $this->batchRows($cachePresentation, $caches, 10, $full),
                'total' => count($caches),
                'more' => max(0, count($caches) - 10),
            ],
            'logs' => [
                'headers' => $logPresentation->batchHeaders(),
                'rows' => $this->batchRows($logPresentation, $logs, 10, $full),
                'total' => count($logs),
                'more' => max(0, count($logs) - 10),
            ],
            'others' => $others,
        ];
    }

    /**
     * Build capped batch rows for a section.
     *
     * @param \Crustum\Speculum\Entry\Presentation\EntryPresentationInterface $presentation Presentation.
     * @param list<\Crustum\Speculum\Entry\EntryResult> $results Section entries.
     * @param int $limit Maximum rows.
     * @param bool $full Whether to skip truncation.
     * @return list<list<string>>
     */
    protected function batchRows(
        EntryPresentationInterface $presentation,
        array $results,
        int $limit,
        bool $full,
    ): array {
        $rows = [];
        foreach (array_slice($results, 0, $limit) as $position => $result) {
            $rows[] = $presentation->batchRow($result, $position + 1, [], $full);
        }

        return $rows;
    }

    /**
     * Aggregate batch query totals (count, time, slow, duplicate groups).
     *
     * @param list<\Crustum\Speculum\Entry\EntryResult> $queries Query entries.
     * @return array{total: int, time: float, slow: int, duplicateGroups: int}
     */
    protected function queryStats(array $queries): array
    {
        $time = 0.0;
        $slow = 0;
        foreach ($queries as $result) {
            $time += (float)($result->content['time'] ?? 0);
            if (!empty($result->content['slow'])) {
                $slow++;
            }
        }

        $groupSizes = array_count_values(array_map(
            $this->queryGroupKey(...),
            $queries,
        ));

        $duplicateGroups = 0;
        foreach ($groupSizes as $size) {
            if ($size > 1) {
                $duplicateGroups++;
            }
        }

        return [
            'total' => count($queries),
            'time' => round($time, 2),
            'slow' => $slow,
            'duplicateGroups' => $duplicateGroups,
        ];
    }

    /**
     * Group key for duplicate detection (statement hash, falling back to SQL).
     *
     * @param \Crustum\Speculum\Entry\EntryResult $result Query entry.
     * @return string
     */
    protected function queryGroupKey(EntryResult $result): string
    {
        $hash = $result->content['hash'] ?? null;
        if (is_string($hash) && $hash !== '') {
            return $hash;
        }

        return (string)($result->content['sql'] ?? '');
    }

    /**
     * Flag repeated (DUP) and slow (SLOW) batch queries by entry id.
     *
     * @param list<\Crustum\Speculum\Entry\EntryResult> $queries Query entries.
     * @return array<string,list<string>>
     */
    protected function queryFlags(array $queries): array
    {
        $grouped = [];
        foreach ($queries as $result) {
            $grouped[$this->queryGroupKey($result)][] = (string)$result->id;
        }

        $flags = [];
        foreach ($queries as $result) {
            $id = (string)$result->id;
            $entryFlags = [];
            if (count($grouped[$this->queryGroupKey($result)] ?? []) > 1) {
                $entryFlags[] = 'DUP';
            }

            if (!empty($result->content['slow'])) {
                $entryFlags[] = 'SLOW';
            }

            if ($entryFlags !== []) {
                $flags[$id] = $entryFlags;
            }
        }

        return $flags;
    }

    /**
     * Count cache hits and misses for the batch header.
     *
     * @param list<\Crustum\Speculum\Entry\EntryResult> $entries Cache entries.
     * @return array{hits: int, misses: int, total: int, rate: float|null}
     */
    protected function cacheStats(array $entries): array
    {
        $hits = 0;
        $misses = 0;
        foreach ($entries as $result) {
            if (($result->content['type'] ?? null) === 'hit') {
                $hits++;
            } elseif (($result->content['type'] ?? null) === 'missed') {
                $misses++;
            }
        }

        $total = $hits + $misses;

        return [
            'hits' => $hits,
            'misses' => $misses,
            'total' => $total,
            'rate' => $total > 0 ? $hits / $total : null,
        ];
    }
}
