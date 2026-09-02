<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\Datasource\EntityInterface;
use Crustum\Notification\AnonymousNotifiable;
use Crustum\Notification\Notification;

/**
 * Named notification fixture for watcher tests (mail channel).
 */
class TestMailNotification extends Notification
{
    /**
     * @inheritDoc
     */
    public function via(EntityInterface|AnonymousNotifiable $notifiable): array
    {
        return ['mail'];
    }
}
