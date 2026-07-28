<?php
declare(strict_types=1);

namespace MongoDB\Driver\Monitoring;

/**
 * Stub for environments without ext-mongodb (tests / PHPStan).
 */
class CommandStartedEvent
{
    /**
     * @return int|string
     */
    public function getRequestId(): int|string
    {
        return 0;
    }

    /**
     * @return string
     */
    public function getCommandName(): string
    {
        return '';
    }

    /**
     * @return object|array<string, mixed>
     */
    public function getCommand(): object|array
    {
        return [];
    }

    /**
     * @return string
     */
    public function getDatabaseName(): string
    {
        return '';
    }
}
