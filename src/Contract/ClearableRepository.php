<?php
declare(strict_types=1);

namespace Crustum\Speculum\Contract;

/**
 * Contract for clearing all Speculum entries.
 */
interface ClearableRepository
{
    /**
     * Clear all of the entries.
     *
     * @return void
     */
    public function clear(): void;
}
