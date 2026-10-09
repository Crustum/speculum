<?php
declare(strict_types=1);

namespace Crustum\Speculum\Contract;

use Crustum\Speculum\Entry\EntryResult;
use Crustum\Speculum\Storage\EntryQueryOptions;

/**
 * Contract for storing and retrieving Speculum entries.
 */
interface EntriesRepository
{
    /**
     * Return an entry with the given ID.
     *
     * @param string $id Entry UUID.
     * @return \Crustum\Speculum\Entry\EntryResult
     */
    public function find(string $id): EntryResult;

    /**
     * Return entries of a given type.
     *
     * @param list<string>|string|null $type Entry type, list of types, or null for all.
     * @param \Crustum\Speculum\Storage\EntryQueryOptions $options Query options.
     * @return list<\Crustum\Speculum\Entry\EntryResult>
     */
    public function get(string|array|null $type, EntryQueryOptions $options): array;

    /**
     * Return distinct batch IDs matching a short hexadecimal prefix.
     *
     * @param string $prefix Raw batch UUID prefix.
     * @return list<string>
     */
    public function batchIdsByPrefix(string $prefix): array;

    /**
     * Store the given entries.
     *
     * @param list<\Crustum\Speculum\Entry\IncomingEntry> $entries Entries to store.
     * @return void
     */
    public function store(array $entries): void;

    /**
     * Store the given entry updates and return failed updates.
     *
     * @param list<\Crustum\Speculum\Entry\EntryUpdate> $updates Entry updates.
     * @return list<\Crustum\Speculum\Entry\EntryUpdate>
     */
    public function update(array $updates): array;

    /**
     * Load the monitored tags from storage.
     *
     * @return void
     */
    public function loadMonitoredTags(): void;

    /**
     * Determine if any of the given tags are currently being monitored.
     *
     * @param list<string> $tags Tags to check.
     * @return bool
     */
    public function isMonitoring(array $tags): bool;

    /**
     * Get the list of tags currently being monitored.
     *
     * @return list<string>
     */
    public function monitoring(): array;

    /**
     * Begin monitoring the given list of tags.
     *
     * @param list<string> $tags Tags to monitor.
     * @return void
     */
    public function monitor(array $tags): void;

    /**
     * Stop monitoring the given list of tags.
     *
     * @param list<string> $tags Tags to stop monitoring.
     * @return void
     */
    public function stopMonitoring(array $tags): void;
}
