<?php
declare(strict_types=1);

namespace Crustum\Speculum\Queue;

use Cake\Queue\QueueManager;
use Crustum\Speculum\Queue\Job\ProcessPendingUpdatesJob;

/**
 * Dispatches Speculum jobs via cakephp/queue QueueManager.
 */
class CakeQueueJobDispatcher implements JobDispatcherInterface
{
    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return class_exists(QueueManager::class);
    }

    /**
     * @inheritDoc
     */
    public function push(array $data, array $options = []): void
    {
        QueueManager::push(ProcessPendingUpdatesJob::class, $data, $options);
    }
}
