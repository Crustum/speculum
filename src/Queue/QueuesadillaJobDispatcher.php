<?php
declare(strict_types=1);

namespace Crustum\Speculum\Queue;

use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Queue\Job\ProcessPendingUpdatesQueuesadillaJob;
use Crustum\Speculum\Registry\WatcherRegistry;
use RuntimeException;

/**
 * Dispatches Speculum jobs via Josegonzalez CakeQueuesadilla Queue::push().
 */
class QueuesadillaJobDispatcher implements JobDispatcherInterface
{
    protected const QUEUE_CLASS = 'Josegonzalez\CakeQueuesadilla\Queue\Queue';

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return WatcherRegistry::isSoftAvailable(SoftFeature::Queuesadilla)
            && class_exists(self::QUEUE_CLASS);
    }

    /**
     * @inheritDoc
     */
    public function push(array $data, array $options = []): void
    {
        $queueClass = self::QUEUE_CLASS;
        $push = [$queueClass, 'push'];
        if (!class_exists($queueClass) || !is_callable($push)) {
            throw new RuntimeException('Josegonzalez CakeQueuesadilla Queue::push is not available.');
        }

        $pushOptions = [];
        $config = $options['config'] ?? null;
        if (is_string($config) && $config !== '') {
            $pushOptions['config'] = $config;
        }

        $queue = $options['queue'] ?? null;
        if (is_string($queue) && $queue !== '') {
            $pushOptions['queue'] = $queue;
        }

        $delay = $options['delay'] ?? null;
        if (is_numeric($delay) && (int)$delay > 0) {
            $pushOptions['delay'] = (int)$delay;
        }

        $push(
            [ProcessPendingUpdatesQueuesadillaJob::class, 'perform'],
            $data,
            $pushOptions,
        );
    }
}
