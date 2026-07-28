<?php
declare(strict_types=1);

namespace Crustum\Speculum\Model\Entity;

use Cake\ORM\Entity;

/**
 * Speculum entry tag entity.
 *
 * @property string $entry_uuid
 * @property string $tag
 */
class SpeculumEntriesTag extends Entity
{
    /**
     * Mass-assignment accessibility map.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'entry_uuid' => false,
        'tag' => false,
    ];
}
