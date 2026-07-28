<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\ORM\Entity;
use Crustum\Notification\AnonymousNotifiable;
use Crustum\Notification\Notification;
use Crustum\Notification\ShouldQueueInterface;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\NotificationWatcher;

/**
 * Notification watcher tests.
 *
 * Requires crustum/notification (Speculum composer require-dev).
 */
class NotificationWatcherTest extends TestCaseBase
{
    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->markSoftPluginLoaded('Crustum/Notification');
    }

    /**
     * @return void
     */
    public function testNotificationWatcherRecordsModelNotificationSent(): void
    {
        $watcher = new NotificationWatcher(['enabled' => true]);
        $watcher->register();

        $user = new Entity(['id' => 7, 'username' => 'admiral']);
        $notification = new class extends Notification {
            public function via(EntityInterface|AnonymousNotifiable $notifiable): array
            {
                return ['database'];
            }
        };

        EventManager::instance()->dispatch(new Event('Model.Notification.sent', null, [
            'notifiable' => $user,
            'notification' => $notification,
            'channel' => 'database',
            'response' => null,
        ]));

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(EntryType::Notification->value, $entries[0]->type);
        $this->assertSame($notification::class, $entries[0]->content['notification']);
        $this->assertFalse($entries[0]->content['queued']);
        $this->assertSame(Entity::class . ':7', $entries[0]->content['notifiable']);
        $this->assertSame('database', $entries[0]->content['channel']);
    }

    /**
     * @return void
     */
    public function testNotificationWatcherFormatsAnonymousNotifiable(): void
    {
        $watcher = new NotificationWatcher(['enabled' => true]);
        $anonymous = (new AnonymousNotifiable())->route('mail', 'speculum@example.com');
        $notification = new class extends Notification {
            public function via(EntityInterface|AnonymousNotifiable $notifiable): array
            {
                return ['mail'];
            }
        };

        $watcher->record([
            'notifiable' => $anonymous,
            'notification' => $notification,
            'channel' => 'mail',
            'response' => null,
        ]);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertStringContainsString('speculum@example.com', (string)$entries[0]->content['notifiable']);
        $this->assertSame('mail', $entries[0]->content['channel']);
    }

    /**
     * @return void
     */
    public function testNotificationWatcherMarksQueuedNotifications(): void
    {
        $watcher = new NotificationWatcher(['enabled' => true]);
        $notification = new class extends Notification implements ShouldQueueInterface {
            public function via(EntityInterface|AnonymousNotifiable $notifiable): array
            {
                return ['database'];
            }
        };

        $watcher->record([
            'notifiable' => new Entity(['id' => 1]),
            'notification' => $notification,
            'channel' => 'database',
        ]);

        $entries = $this->loadSpeculumEntries();
        $this->assertTrue($entries[0]->content['queued']);
    }
}
