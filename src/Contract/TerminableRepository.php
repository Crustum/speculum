<?php
declare(strict_types=1);

namespace Crustum\Speculum\Contract;

/**
 * Contract for repository cleanup after storing entries.
 */
interface TerminableRepository
{
    /**
     * Perform any clean-up tasks needed after storing Speculum entries.
     *
     * @return void
     */
    public function terminate(): void;
}
