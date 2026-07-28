<?php
declare(strict_types=1);

namespace Crustum\Speculum\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Model\Entity\SpeculumEntry;
use Crustum\Speculum\Storage\EntryQueryOptions;
use DateTimeInterface;

/**
 * Speculum entries table.
 *
 * @method \Crustum\Speculum\Model\Entity\SpeculumEntry get(mixed $primaryKey, array<string, mixed>|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Crustum\Speculum\Model\Entity\SpeculumEntry newEntity(array<string, mixed> $data, array<string, mixed> $options = [])
 * @method array<\Crustum\Speculum\Model\Entity\SpeculumEntry> newEntities(array<int, array<string, mixed>> $data, array<string, mixed> $options = [])
 * @method \Crustum\Speculum\Model\Entity\SpeculumEntry|false save(\Cake\Datasource\EntityInterface $entity, array<string, mixed> $options = [])
 */
class SpeculumEntriesTable extends Table
{
    /**
     * @inheritDoc
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('speculum_entries');
        $this->setPrimaryKey('uuid');
        $this->setEntityClass(SpeculumEntry::class);
        $this->setDisplayField('uuid');
        $this->getSchema()->setColumnType('content', 'json');

        $this->hasMany('SpeculumEntriesTags', [
            'className' => SpeculumEntriesTagsTable::class,
            'foreignKey' => 'entry_uuid',
            'bindingKey' => 'uuid',
            'propertyName' => 'tags',
            'dependent' => true,
            'strategy' => 'select',
        ]);
    }

    /**
     * Find a single entry by UUID, optionally containing tags.
     *
     * @param string $uuid Entry UUID.
     * @param bool $withTags Whether to contain tag rows.
     * @return \Crustum\Speculum\Model\Entity\SpeculumEntry|null
     */
    public function findByUuid(string $uuid, bool $withTags = true): ?SpeculumEntry
    {
        $query = $this->find()->where([$this->aliasField('uuid') => $uuid]);
        if ($withTags) {
            $query->contain(['SpeculumEntriesTags']);
        }

        /** @var \Crustum\Speculum\Model\Entity\SpeculumEntry|null $entry */
        $entry = $query->first();

        return $entry;
    }

    /**
     * Find entries filtered by type and query options.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Crustum\Speculum\Model\Entity\SpeculumEntry> $query Query.
     * @param list<string>|string|null $entryType Entry type filter.
     * @param \Crustum\Speculum\Storage\EntryQueryOptions|null $options Query options.
     * @return \Cake\ORM\Query\SelectQuery<\Crustum\Speculum\Model\Entity\SpeculumEntry>
     */
    public function findFiltered(
        SelectQuery $query,
        string|array|null $entryType = null,
        ?EntryQueryOptions $options = null,
    ): SelectQuery {
        $opts = $options ?? new EntryQueryOptions();

        if ($opts->orderBy === 'duration') {
            $query->where([$this->aliasField('duration') . ' IS NOT' => null]);
            if ($opts->orderDirection === 'asc') {
                $query->orderByAsc($this->aliasField('duration'));
            } else {
                $query->orderByDesc($this->aliasField('duration'));
            }

            $query->orderByDesc($this->aliasField('sequence'));
        } else {
            $query->orderByDesc($this->aliasField('sequence'));
        }

        if (is_array($entryType)) {
            $types = array_values(array_filter($entryType, static fn(string $item): bool => $item !== ''));
            if ($types !== []) {
                $query->where([$this->aliasField('type') . ' IN' => $types]);
            }
        } elseif ($entryType !== null && $entryType !== '') {
            $query->where([$this->aliasField('type') => $entryType]);
        }

        if ($opts->batchId) {
            $query->where([$this->aliasField('batch_id') => $opts->batchId]);
        }

        if ($opts->familyHash) {
            $query->where([$this->aliasField('family_hash') => $opts->familyHash]);
        }

        if ($opts->beforeSequence !== null && $opts->beforeSequence !== '') {
            if ($opts->orderBy === 'duration' && $opts->beforeDuration !== null) {
                $durationField = $this->aliasField('duration');
                $sequenceField = $this->aliasField('sequence');
                if ($opts->orderDirection === 'asc') {
                    $query->where([
                        'OR' => [
                            [$durationField . ' >' => $opts->beforeDuration],
                            [
                                $durationField => $opts->beforeDuration,
                                $sequenceField . ' <' => $opts->beforeSequence,
                            ],
                        ],
                    ]);
                } else {
                    $query->where([
                        'OR' => [
                            [$durationField . ' <' => $opts->beforeDuration],
                            [
                                $durationField => $opts->beforeDuration,
                                $sequenceField . ' <' => $opts->beforeSequence,
                            ],
                        ],
                    ]);
                }
            } else {
                $query->where([$this->aliasField('sequence') . ' <' => $opts->beforeSequence]);
            }
        }

        if ($opts->afterSequence !== null && $opts->afterSequence !== '') {
            $query->where([$this->aliasField('sequence') . ' >' => $opts->afterSequence]);
        }

        if ($opts->uuids) {
            $query->where([$this->aliasField('uuid') . ' IN' => $opts->uuids]);
        }

        if ($opts->minDuration !== null) {
            $query->where([$this->aliasField('duration') . ' >=' => $opts->minDuration]);
        }

        if ($opts->tag) {
            $tags = array_values(array_filter(array_map(trim(...), explode(',', $opts->tag))));
            if ($tags !== []) {
                $taggedUuids = $this->getAssociation('SpeculumEntriesTags')
                    ->find()
                    ->select(['SpeculumEntriesTags.entry_uuid'])
                    ->where(['SpeculumEntriesTags.tag IN' => $tags]);
                $query->where([
                    $this->aliasField('uuid') . ' IN' => $taggedUuids,
                ]);
            }
        }

        if (!$opts->familyHash && !$opts->tag && !$opts->batchId) {
            $query->where([$this->aliasField('should_display_on_index') => true]);
        }

        if ($opts->limit >= 0) {
            $query->limit($opts->limit);
        }

        return $query;
    }

    /**
     * Insert prepared entry rows (chunked by caller).
     *
     * @param list<array<string, mixed>> $rows Row data.
     * @return void
     */
    public function insertRows(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $entities = $this->newEntities($rows, [
            'accessibleFields' => ['*' => true],
        ]);
        $this->saveMany($entities, [
            'atomic' => false,
            'checkExisting' => false,
        ]);
    }

    /**
     * Hide previous exception occurrences for a family hash from the index.
     *
     * @param string|null $familyHash Exception family hash.
     * @return void
     */
    public function hidePreviousExceptionOccurrences(?string $familyHash): void
    {
        if ($familyHash === null || $familyHash === '') {
            return;
        }

        $this->updateAll(
            ['should_display_on_index' => false],
            [
                'type' => EntryType::Exception->value,
                'family_hash' => $familyHash,
                'should_display_on_index' => true,
            ],
        );
    }

    /**
     * Count stored occurrences for an exception family hash.
     *
     * @param string|null $familyHash Exception family hash.
     * @return int
     */
    public function countExceptionOccurrences(?string $familyHash): int
    {
        if ($familyHash === null || $familyHash === '') {
            return 0;
        }

        return $this->find()
            ->where([
                'type' => EntryType::Exception->value,
                'family_hash' => $familyHash,
            ])
            ->count();
    }

    /**
     * Return entry UUIDs older than `$before` for pruning (ordered ascending).
     *
     * @param \DateTimeInterface $before Cutoff datetime.
     * @param bool $keepExceptions Whether to exclude exception entries.
     * @param int $limit Chunk size.
     * @return list<string>
     */
    public function uuidsOlderThan(DateTimeInterface $before, bool $keepExceptions, int $limit): array
    {
        $query = $this->find()
            ->select(['uuid'])
            ->where(['created <' => $before->format('Y-m-d H:i:s')])
            ->orderByAsc('sequence')
            ->limit($limit);

        if ($keepExceptions) {
            $query->where(['type !=' => EntryType::Exception->value]);
        }

        /** @var list<string> $uuids */
        $uuids = $query->all()->extract('uuid')->map(static fn(mixed $uuid): string => (string)$uuid)->toList();

        return $uuids;
    }

    /**
     * Return the next chunk of entry UUIDs for a full clear.
     *
     * @param int $limit Chunk size.
     * @return list<string>
     */
    public function uuidsForClear(int $limit): array
    {
        /** @var list<string> $uuids */
        $uuids = $this->find()
            ->select(['uuid'])
            ->orderByAsc('sequence')
            ->limit($limit)
            ->all()
            ->extract('uuid')
            ->map(static fn(mixed $uuid): string => (string)$uuid)
            ->toList();

        return $uuids;
    }

    /**
     * Delete entries by UUID list.
     *
     * @param list<string> $uuids Entry UUIDs.
     * @return int Rows deleted.
     */
    public function deleteByUuids(array $uuids): int
    {
        if ($uuids === []) {
            return 0;
        }

        return $this->deleteAll(['uuid IN' => $uuids]);
    }

    /**
     * Find one entry by UUID and type (for updates).
     *
     * @param string $uuid Entry UUID.
     * @param string $type Entry type.
     * @return \Crustum\Speculum\Model\Entity\SpeculumEntry|null
     */
    public function findByUuidAndType(string $uuid, string $type): ?SpeculumEntry
    {
        /** @var \Crustum\Speculum\Model\Entity\SpeculumEntry|null $entry */
        $entry = $this->find()
            ->where([
                'uuid' => $uuid,
                'type' => $type,
            ])
            ->first();

        return $entry;
    }
}
