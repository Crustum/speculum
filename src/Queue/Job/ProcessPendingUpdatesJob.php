<?php
declare(strict_types=1);

namespace Crustum\Speculum\Queue\Job;

use Cake\Queue\Job\JobInterface;
use Cake\Queue\Job\Message;
use Crustum\Speculum\Queue\PendingUpdatesProcessor;
use Interop\Queue\Processor;

/**
 * cakephp/queue job that retries failed Speculum entry updates.
 */
class ProcessPendingUpdatesJob implements JobInterface
{
    /**
     * Process queued Speculum entry updates and requeue failures.
     *
     * @param \Cake\Queue\Job\Message $message Queue message.
     * @return string|null
     */
    public function execute(Message $message): ?string
    {
        $pending = $message->getArgument('pendingUpdates', []);
        $attempt = (int)$message->getArgument('attempt', 0);
        if (!is_array($pending)) {
            $pending = [];
        }

        (new PendingUpdatesProcessor())->process($pending, $attempt);

        return Processor::ACK;
    }
}
