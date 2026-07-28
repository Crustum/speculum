<?php
declare(strict_types=1);

namespace MongoDB\Driver\Monitoring;

/**
 * Stub for environments without ext-mongodb (tests / PHPStan).
 */
interface CommandSubscriber
{
    /**
     * @param \MongoDB\Driver\Monitoring\CommandStartedEvent $event Event.
     * @return void
     */
    public function commandStarted(CommandStartedEvent $event): void;

    /**
     * @param \MongoDB\Driver\Monitoring\CommandSucceededEvent $event Event.
     * @return void
     */
    public function commandSucceeded(CommandSucceededEvent $event): void;

    /**
     * @param \MongoDB\Driver\Monitoring\CommandFailedEvent $event Event.
     * @return void
     */
    public function commandFailed(CommandFailedEvent $event): void;
}
