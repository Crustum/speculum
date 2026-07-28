<?php
declare(strict_types=1);

namespace Crustum\Speculum\Storage;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\Locator\LocatorAwareTrait;
use Crustum\Speculum\Contract\ClearableRepository;
use Crustum\Speculum\Contract\EntriesRepository;
use Crustum\Speculum\Contract\PrunableRepository;
use Crustum\Speculum\Contract\TerminableRepository;
use Crustum\Speculum\Entry\EntryResult;
use Crustum\Speculum\Entry\EntryUpdate;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Model\Entity\SpeculumEntry;
use Crustum\Speculum\Model\Table\SpeculumEntriesTable;
use Crustum\Speculum\Model\Table\SpeculumEntriesTagsTable;
use Crustum\Speculum\Model\Table\SpeculumMonitoringTable;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Throwable;

/**
 * Database-backed Speculum entries repository (CakeORM tables).
 */
class DatabaseEntriesRepository implements
    EntriesRepository,
    ClearableRepository,
    PrunableRepository,
    TerminableRepository
{
    use LocatorAwareTrait;

    /**
     * Database connection name used for Speculum tables.
     *
     * @var string
     */
    protected string $connectionName;

    /**
     * Chunk size for bulk insert and delete operations.
     *
     * @var int<1, max>
     */
    protected int $chunkSize = 1000;

    /**
     * Cached monitored tags list for the current process.
     *
     * @var list<string>|null
     */
    protected ?array $monitoredTags = null;

    /**
     * Speculum entries table.
     *
     * @var \Crustum\Speculum\Model\Table\SpeculumEntriesTable
     */
    protected SpeculumEntriesTable $entries;

    /**
     * Speculum entry tags table.
     *
     * @var \Crustum\Speculum\Model\Table\SpeculumEntriesTagsTable
     */
    protected SpeculumEntriesTagsTable $tags;

    /**
     * Speculum monitoring tags table.
     *
     * @var \Crustum\Speculum\Model\Table\SpeculumMonitoringTable
     */
    protected SpeculumMonitoringTable $monitoring;

    /**
     * Create a database-backed entries repository.
     *
     * @param string $connectionName Connection name.
     * @param int|null $chunkSize Insert/delete chunk size.
     */
    public function __construct(string $connectionName = 'default', ?int $chunkSize = null)
    {
        $this->connectionName = $connectionName;
        if ($chunkSize !== null) {
            $this->chunkSize = max(1, $chunkSize);
        }

        $connection = ConnectionManager::get($connectionName);
        /** @var \Crustum\Speculum\Model\Table\SpeculumEntriesTable $entries */
        $entries = $this->fetchTable('Crustum/Speculum.SpeculumEntries', [
            'connection' => $connection,
        ]);
        /** @var \Crustum\Speculum\Model\Table\SpeculumEntriesTagsTable $tags */
        $tags = $this->fetchTable('Crustum/Speculum.SpeculumEntriesTags', [
            'connection' => $connection,
        ]);
        /** @var \Crustum\Speculum\Model\Table\SpeculumMonitoringTable $monitoring */
        $monitoring = $this->fetchTable('Crustum/Speculum.SpeculumMonitoring', [
            'connection' => $connection,
        ]);

        $this->entries = $entries;
        $this->tags = $tags;
        $this->monitoring = $monitoring;
    }

    /**
     * @inheritDoc
     */
    public function find(string $id): EntryResult
    {
        $entry = $this->entries->findByUuid($id, true);
        if (!$entry instanceof SpeculumEntry) {
            throw new Exception(sprintf('Speculum entry [%s] not found.', $id));
        }

        return $this->toResult($entry, $entry->tagList());
    }

    /**
     * @inheritDoc
     */
    public function get(string|array|null $type, EntryQueryOptions $options): array
    {
        /** @var list<\Crustum\Speculum\Model\Entity\SpeculumEntry> $rows */
        $rows = $this->entries->find('filtered', entryType: $type, options: $options)->all()->toList();

        $results = [];
        foreach ($rows as $row) {
            $content = $row->content;
            if (!is_array($content)) {
                continue;
            }

            $results[] = $this->toResult($row, []);
        }

        return $results;
    }

    /**
     * @inheritDoc
     */
    public function store(array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $exceptions = [];
        $regular = [];
        foreach ($entries as $entry) {
            if ($entry->isException()) {
                $exceptions[] = $entry;
            } else {
                $regular[] = $entry;
            }
        }

        $this->storeExceptions($exceptions);
        $this->insertEntries($regular);
        $this->storeTags($this->tagsByUuid($regular));
        $this->storeTags($this->tagsByUuid($exceptions));
    }

    /**
     * Store exception entries with occurrence counts and family hashing.
     *
     * @param list<\Crustum\Speculum\Entry\IncomingEntry> $exceptions Exception entries.
     * @return void
     */
    protected function storeExceptions(array $exceptions): void
    {
        $entries = $this->entries;

        foreach (array_chunk($exceptions, $this->chunkSize) as $chunk) {
            $rows = [];
            foreach ($chunk as $exception) {
                $familyHash = $exception->familyHash();
                $occurrences = $entries->countExceptionOccurrences($familyHash);

                try {
                    $entries->hidePreviousExceptionOccurrences($familyHash);
                } catch (Throwable) {
                }

                $data = $exception->toArray();
                $data['family_hash'] = $familyHash;
                $data['should_display_on_index'] = true;
                $data['content'] = array_merge($exception->content, ['occurrences' => $occurrences + 1]);
                $rows[] = $data;
            }

            $entries->insertRows($rows);
        }
    }

    /**
     * Insert regular non-exception entries in chunks.
     *
     * @param list<\Crustum\Speculum\Entry\IncomingEntry> $entries Entries to insert.
     * @return void
     */
    protected function insertEntries(array $entries): void
    {
        foreach (array_chunk($entries, $this->chunkSize) as $chunk) {
            $rows = [];
            foreach ($chunk as $entry) {
                $data = $entry->toArray();
                $data['should_display_on_index'] = true;
                $rows[] = $data;
            }

            $this->entries->insertRows($rows);
        }
    }

    /**
     * Persist entry tags keyed by UUID.
     *
     * @param array<string, list<string>> $tagsByUuid Tags keyed by entry UUID.
     * @return void
     */
    protected function storeTags(array $tagsByUuid): void
    {
        $toInsert = [];
        foreach ($tagsByUuid as $uuid => $tags) {
            foreach ($tags as $tag) {
                $toInsert[] = [
                    'entry_uuid' => $uuid,
                    'tag' => $tag,
                ];
                if (count($toInsert) >= $this->chunkSize) {
                    $this->tags->insertIgnoringDuplicates($toInsert);
                    $toInsert = [];
                }
            }
        }

        if ($toInsert !== []) {
            $this->tags->insertIgnoringDuplicates($toInsert);
        }
    }

    /**
     * @inheritDoc
     */
    public function update(array $updates): array
    {
        $failed = [];
        $entries = $this->entries;

        foreach ($updates as $update) {
            $entry = $entries->findByUuidAndType($update->uuid, $update->type);
            if (!$entry instanceof SpeculumEntry) {
                $failed[] = $update;
                continue;
            }

            $existing = is_array($entry->content) ? $entry->content : [];
            $merged = array_merge($existing, $update->changes);
            $entry->set('content', $merged);
            $duration = IncomingEntry::durationFromContent($merged);
            if ($duration !== null || array_key_exists('duration', $update->changes) || array_key_exists('time', $update->changes)) {
                $entry->set('duration', $duration);
            }

            $entries->save($entry, [
                'atomic' => false,
                'checkExisting' => false,
            ]);

            $this->updateTags($update);
        }

        return $failed;
    }

    /**
     * Apply tag additions and removals for an entry update.
     *
     * @param \Crustum\Speculum\Entry\EntryUpdate $entry Entry update.
     * @return void
     */
    protected function updateTags(EntryUpdate $entry): void
    {
        $tags = $this->tags;
        $added = [];
        foreach ($entry->tagsChanges['added'] as $tag) {
            $added[] = [
                'entry_uuid' => $entry->uuid,
                'tag' => $tag,
            ];
        }

        if ($added !== []) {
            $tags->insertIgnoringDuplicates($added);
        }

        foreach ($entry->tagsChanges['removed'] as $tag) {
            $tags->deleteTag($entry->uuid, $tag);
        }
    }

    /**
     * @inheritDoc
     */
    public function loadMonitoredTags(): void
    {
        try {
            $this->monitoredTags = $this->monitoring();
        } catch (Throwable) {
            $this->monitoredTags = [];
        }
    }

    /**
     * @inheritDoc
     */
    public function isMonitoring(array $tags): bool
    {
        if ($this->monitoredTags === null) {
            $this->loadMonitoredTags();
        }

        return array_intersect($tags, $this->monitoredTags ?? []) !== [];
    }

    /**
     * @inheritDoc
     */
    public function monitoring(): array
    {
        return $this->monitoring->listTags();
    }

    /**
     * @inheritDoc
     */
    public function monitor(array $tags): void
    {
        $tags = array_values(array_diff($tags, $this->monitoring()));
        if ($tags !== []) {
            $this->monitoring->addTags($tags);
        }

        $this->monitoredTags = null;
    }

    /**
     * @inheritDoc
     */
    public function stopMonitoring(array $tags): void
    {
        if ($tags === []) {
            return;
        }

        $this->monitoring->removeTags($tags);
        $this->monitoredTags = null;
    }

    /**
     * @inheritDoc
     */
    public function prune(DateTimeInterface $before, bool $keepExceptions): int
    {
        $totalDeleted = 0;
        $entries = $this->entries;
        $tags = $this->tags;

        do {
            $uuids = $entries->uuidsOlderThan($before, $keepExceptions, $this->chunkSize);
            if ($uuids === []) {
                break;
            }

            $tags->deleteByEntryUuids($uuids);
            $deleted = $entries->deleteByUuids($uuids);
            $totalDeleted += $deleted;
        } while ($deleted !== 0);

        return $totalDeleted;
    }

    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        do {
            $uuids = $this->entries->uuidsForClear($this->chunkSize);
            if ($uuids === []) {
                break;
            }

            $this->tags->deleteByEntryUuids($uuids);
            $this->entries->deleteByUuids($uuids);
        } while (true);

        do {
            $chunk = $this->monitoring->tagsForClear($this->chunkSize);
            if ($chunk === []) {
                break;
            }

            $this->monitoring->removeTags($chunk);
        } while (true);
    }

    /**
     * @inheritDoc
     */
    public function terminate(): void
    {
        $this->monitoredTags = null;
    }

    /**
     * Index entry tags by UUID.
     *
     * @param list<\Crustum\Speculum\Entry\IncomingEntry> $entries Entries.
     * @return array<string, list<string>>
     */
    protected function tagsByUuid(array $entries): array
    {
        $result = [];
        foreach ($entries as $entry) {
            $result[$entry->uuid] = $entry->tags;
        }

        return $result;
    }

    /**
     * Convert a Speculum entry entity into an EntryResult.
     *
     * @param \Crustum\Speculum\Model\Entity\SpeculumEntry $entry Entry entity.
     * @param list<string> $tags Tags.
     * @return \Crustum\Speculum\Entry\EntryResult
     */
    protected function toResult(SpeculumEntry $entry, array $tags): EntryResult
    {
        $content = is_array($entry->content) ? $entry->content : [];

        $createdAt = $entry->created ?? 'now';
        if (!$createdAt instanceof DateTimeInterface) {
            $createdAt = new DateTimeImmutable((string)$createdAt);
        }

        return new EntryResult(
            (string)$entry->uuid,
            $entry->sequence ?? null,
            (string)$entry->batch_id,
            (string)$entry->type,
            $entry->family_hash,
            $content,
            $createdAt,
            $tags,
            $entry->duration,
        );
    }
}
