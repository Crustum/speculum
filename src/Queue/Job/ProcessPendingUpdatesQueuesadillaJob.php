<?php
declare(strict_types=1);

namespace Crustum\Speculum\Queue\Job;

use Crustum\Speculum\Queue\PendingUpdatesProcessor;

/**
 * Queuesadilla callable job that retries failed Speculum entry updates.
 */
class ProcessPendingUpdatesQueuesadillaJob
{
    /**
     * Perform the pending-updates job for a Queuesadilla worker.
     *
     * @param object $job Queuesadilla job wrapper with data()/data($key).
     * @return void
     */
    public function perform(object $job): void
    {
        $pending = [];
        $attempt = 0;

        if (method_exists($job, 'data')) {
            $pendingRaw = $job->data('pendingUpdates', []);
            $pending = is_array($pendingRaw) ? $pendingRaw : [];
            $attempt = (int)$job->data('attempt', 0);
        }

        (new PendingUpdatesProcessor())->process($pending, $attempt);
    }
}
