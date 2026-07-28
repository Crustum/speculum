<?php
declare(strict_types=1);

namespace Crustum\Speculum\Queue\Task;

use Crustum\Speculum\Queue\PendingUpdatesProcessor;
use Queue\Queue\Task;

/**
 * Dereuromark Queue task that retries failed Speculum entry updates.
 *
 * Discovered as `Crustum/Speculum.ProcessPendingUpdates`.
 */
class ProcessPendingUpdatesTask extends Task
{
    /**
     * @inheritDoc
     */
    public function run(array $data, int $jobId): void
    {
        $pending = $data['pendingUpdates'] ?? [];
        if (!is_array($pending)) {
            $pending = [];
        }

        (new PendingUpdatesProcessor())->process($pending, (int)($data['attempt'] ?? 0));
    }

    /**
     * @inheritDoc
     */
    public function description(): ?string
    {
        return 'Retry failed Speculum entry updates';
    }
}
