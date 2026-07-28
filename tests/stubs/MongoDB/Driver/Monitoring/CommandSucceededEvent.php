<?php
declare(strict_types=1);

namespace MongoDB\Driver\Monitoring;

/**
 * Stub for environments without ext-mongodb (tests / PHPStan).
 */
class CommandSucceededEvent
{
    /**
     * @return int|string
     */
    public function getRequestId(): int|string
    {
        return 0;
    }
}
