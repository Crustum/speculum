<?php
declare(strict_types=1);

namespace MongoDB\Driver\Monitoring;

use Exception;
use RuntimeException;

/**
 * Stub for environments without ext-mongodb (tests / PHPStan).
 */
class CommandFailedEvent
{
    /**
     * @return int|string
     */
    public function getRequestId(): int|string
    {
        return 0;
    }

    /**
     * @return \Exception
     */
    public function getError(): Exception
    {
        return new RuntimeException('stub');
    }
}
