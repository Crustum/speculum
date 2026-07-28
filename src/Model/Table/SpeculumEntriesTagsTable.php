<?php
declare(strict_types=1);

namespace Crustum\Speculum\Model\Table;

use Cake\ORM\Table;
use Crustum\Speculum\Model\Entity\SpeculumEntriesTag;
use Throwable;

/**
 * Speculum entry tags table.
 *
 * @method \Crustum\Speculum\Model\Entity\SpeculumEntriesTag newEntity(array<string, mixed> $data, array<string, mixed> $options = [])
 * @method array<\Crustum\Speculum\Model\Entity\SpeculumEntriesTag> newEntities(array<int, array<string, mixed>> $data, array<string, mixed> $options = [])
 */
class SpeculumEntriesTagsTable extends Table
{
    /**
     * @inheritDoc
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('speculum_entries_tags');
        $this->setPrimaryKey(['entry_uuid', 'tag']);
        $this->setEntityClass(SpeculumEntriesTag::class);

        $this->belongsTo('SpeculumEntries', [
            'className' => SpeculumEntriesTable::class,
            'foreignKey' => 'entry_uuid',
            'bindingKey' => 'uuid',
        ]);
    }

    /**
     * Insert tag rows, ignoring duplicate primary-key conflicts.
     *
     * @param list<array{entry_uuid: string, tag: string}> $rows Tag rows.
     * @return void
     */
    public function insertIgnoringDuplicates(array $rows): void
    {
        foreach ($rows as $row) {
            try {
                $this->save($this->newEntity($row, [
                    'accessibleFields' => ['*' => true],
                ]), [
                    'atomic' => false,
                    'checkExisting' => false,
                ]);
            } catch (Throwable) {
            }
        }
    }

    /**
     * Delete tags for an entry UUID list.
     *
     * @param list<string> $uuids Entry UUIDs.
     * @return int
     */
    public function deleteByEntryUuids(array $uuids): int
    {
        if ($uuids === []) {
            return 0;
        }

        return $this->deleteAll(['entry_uuid IN' => $uuids]);
    }

    /**
     * Delete a single entry tag.
     *
     * @param string $entryUuid Entry UUID.
     * @param string $tag Tag value.
     * @return int
     */
    public function deleteTag(string $entryUuid, string $tag): int
    {
        return $this->deleteAll([
            'entry_uuid' => $entryUuid,
            'tag' => $tag,
        ]);
    }
}
