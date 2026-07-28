<?php
declare(strict_types=1);

namespace Crustum\Speculum\Queue;

use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Queue\Task\ProcessPendingUpdatesTask;
use Crustum\Speculum\Registry\WatcherRegistry;
use RuntimeException;

/**
 * Dispatches Speculum jobs via Dereuromark QueuedJobs::createJob().
 */
class DereuromarkJobDispatcher implements JobDispatcherInterface
{
    use LocatorAwareTrait;

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return WatcherRegistry::isSoftAvailable(SoftFeature::DereuromarkQueue);
    }

    /**
     * @inheritDoc
     */
    public function push(array $data, array $options = []): void
    {
        $config = [];
        $delay = $options['delay'] ?? null;
        if (is_numeric($delay) && (int)$delay > 0) {
            $config['notBefore'] = new DateTime('+' . (int)$delay . ' seconds');
        }

        $group = $options['queue'] ?? null;
        if (is_string($group) && $group !== '') {
            $config['group'] = $group;
        }

        $table = $this->fetchTable('Queue.QueuedJobs');
        $createJob = [$table, 'createJob'];
        if (!is_callable($createJob)) {
            throw new RuntimeException('Queue.QueuedJobs::createJob is not available.');
        }

        $createJob(ProcessPendingUpdatesTask::class, $data, $config);
    }
}
