<?php
declare(strict_types=1);

namespace Crustum\Speculum\Model\Entity;

use Cake\ORM\Entity;

/**
 * Speculum entry entity.
 *
 * @property int $sequence
 * @property string $uuid
 * @property string $batch_id
 * @property string|null $family_hash
 * @property bool $should_display_on_index
 * @property string $type
 * @property string|array<string, mixed> $content
 * @property int|null $duration
 * @property \Cake\I18n\DateTime|null $created
 * @property array<\Crustum\Speculum\Model\Entity\SpeculumEntriesTag>|null $tags
 */
class SpeculumEntry extends Entity
{
    /**
     * Mass-assignment accessibility map.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'uuid' => false,
        'batch_id' => true,
        'family_hash' => true,
        'should_display_on_index' => true,
        'type' => true,
        'content' => true,
        'duration' => true,
        'created' => true,
        'tags' => true,
        'sequence' => false,
    ];

    /**
     * Return tag strings for this entry when tags were contained.
     *
     * @return list<string>
     */
    public function tagList(): array
    {
        if (!is_array($this->tags)) {
            return [];
        }

        return array_values(array_map(
            static fn(SpeculumEntriesTag $tag): string => (string)$tag->tag,
            $this->tags,
        ));
    }
}
