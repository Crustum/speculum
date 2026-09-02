<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\Datasource\EntityInterface;
use Crustum\Notification\AnonymousNotifiable;
use Crustum\Notification\Notification;
use Crustum\Notification\ShouldQueueInterface;

/**
 * Named queued notification fixture for watcher tests.
 */
class TestQueuedNotification extends Notification implements ShouldQueueInterface
{
    /**
     * @inheritDoc
     */
    public function via(EntityInterface|AnonymousNotifiable $notifiable): array
    {
        return ['database'];
    }
}
