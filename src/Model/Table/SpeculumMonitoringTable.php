<?php
declare(strict_types=1);

namespace Crustum\Speculum\Model\Table;

use Cake\ORM\Table;
use Crustum\Speculum\Model\Entity\SpeculumMonitoring;

/**
 * Speculum monitoring tags table.
 *
 * @method \Crustum\Speculum\Model\Entity\SpeculumMonitoring newEntity(array<string, mixed> $data, array<string, mixed> $options = [])
 */
class SpeculumMonitoringTable extends Table
{
    /**
     * @inheritDoc
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('speculum_monitoring');
        $this->setPrimaryKey('tag');
        $this->setEntityClass(SpeculumMonitoring::class);
        $this->setDisplayField('tag');
    }

    /**
     * Return all monitored tags.
     *
     * @return list<string>
     */
    public function listTags(): array
    {
        /** @var list<string> $tags */
        $tags = $this->find()
            ->select(['tag'])
            ->orderByAsc('tag')
            ->all()
            ->extract('tag')
            ->map(static fn(mixed $tag): string => (string)$tag)
            ->toList();

        return $tags;
    }

    /**
     * Insert monitoring tags that are not already present.
     *
     * @param list<string> $tags Tags to add.
     * @return void
     */
    public function addTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->save($this->newEntity(['tag' => $tag], [
                'accessibleFields' => ['*' => true],
            ]), [
                'atomic' => false,
                'checkExisting' => false,
            ]);
        }
    }

    /**
     * Remove monitored tags.
     *
     * @param list<string> $tags Tags to remove.
     * @return int
     */
    public function removeTags(array $tags): int
    {
        if ($tags === []) {
            return 0;
        }

        return $this->deleteAll(['tag IN' => $tags]);
    }

    /**
     * Return the next chunk of monitored tags for a full clear.
     *
     * @param int $limit Chunk size.
     * @return list<string>
     */
    public function tagsForClear(int $limit): array
    {
        /** @var list<string> $tags */
        $tags = $this->find()
            ->select(['tag'])
            ->orderByAsc('tag')
            ->limit($limit)
            ->all()
            ->extract('tag')
            ->map(static fn(mixed $tag): string => (string)$tag)
            ->toList();

        return $tags;
    }
}
