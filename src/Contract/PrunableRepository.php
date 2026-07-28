<?php
declare(strict_types=1);

namespace Crustum\Speculum\Contract;

use DateTimeInterface;

/**
 * Contract for pruning old Speculum entries.
 */
interface PrunableRepository
{
    /**
     * Prune all of the entries older than the given date.
     *
     * @param \DateTimeInterface $before Cutoff date.
     * @param bool $keepExceptions Whether to keep exception entries.
     * @return int Number of deleted rows.
     */
    public function prune(DateTimeInterface $before, bool $keepExceptions): int;
}
