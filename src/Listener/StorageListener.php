<?php
declare(strict_types=1);

namespace Crustum\Speculum\Listener;

use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Crustum\Speculum\Event\SpeculumFlushEvent;
use Crustum\Speculum\Recording\WorkerFlushPolicy;
use Crustum\Speculum\Speculum;
use Throwable;

/**
 * Wires flush opportunities: HTTP terminate, CLI afterExecute, shutdown, host flush events.
 */
final class StorageListener
{
    /**
     * Listen for flush opportunities (Server.terminate + Command.afterExecute + shutdown + Speculum.flush).
     *
     * @return void
     */
    public static function register(): void
    {
        $manager = EventManager::instance();

        $manager->on('Server.terminate', function (): void {
            Speculum::store();
        });

        $manager->on('Command.afterExecute', function (): void {
            Speculum::store();
        });

        $flushHandler = static function (EventInterface $event): void {
            $throttled = $event instanceof SpeculumFlushEvent
                ? $event->isThrottled()
                : ($event->getData('throttled') ?? false) === true;

            if ($throttled) {
                WorkerFlushPolicy::maybeStore();

                return;
            }

            Speculum::store();
        };

        $manager->on(SpeculumFlushEvent::FLUSH, $flushHandler);
        $manager->on(SpeculumFlushEvent::class, $flushHandler);

        register_shutdown_function(static function (): void {
            if (Speculum::$entriesQueue !== [] || Speculum::$updatesQueue !== []) {
                try {
                    Speculum::store();
                } catch (Throwable) {
                }
            }
        });
    }
}
