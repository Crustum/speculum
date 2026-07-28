<?php
declare(strict_types=1);

namespace Crustum\Speculum\Model\Entity;

use Cake\ORM\Entity;

/**
 * Monitored tag entity.
 *
 * @property string $tag
 */
class SpeculumMonitoring extends Entity
{
    /**
     * Mass-assignment accessibility map.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'tag' => false,
    ];
}
