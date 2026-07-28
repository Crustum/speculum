<?php
declare(strict_types=1);

namespace MongoDB\Driver\Monitoring;

if (!function_exists(__NAMESPACE__ . '\\addSubscriber')) {
    /**
     * Stub for environments without ext-mongodb.
     *
     * @param \MongoDB\Driver\Monitoring\CommandSubscriber $subscriber Subscriber.
     * @return void
     */
    function addSubscriber(CommandSubscriber $subscriber): void
    {
    }
}
