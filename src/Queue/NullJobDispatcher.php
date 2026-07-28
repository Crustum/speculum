<?php
declare(strict_types=1);

namespace Crustum\Speculum\Queue;

use Cake\Log\Log;

/**
 * No-op dispatcher when no queue backend is available.
 */
class NullJobDispatcher implements JobDispatcherInterface
{
    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function push(array $data, array $options = []): void
    {
        Log::warning(
            'Speculum could not queue pending updates: no queue transport available '
            . '(install cakephp/queue, dereuromark/cakephp-queue, or josegonzalez/cakephp-queuesadilla).',
        );
    }
}
