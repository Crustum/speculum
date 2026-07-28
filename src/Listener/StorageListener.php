<?php
declare(strict_types=1);

namespace Crustum\Speculum\Listener;

use Cake\Event\EventManager;
use Crustum\Speculum\Speculum;
use Throwable;

/**
 * Wires flush opportunities: HTTP terminate, CLI afterExecute, shutdown.
 */
final class StorageListener
{
    /**
     * Listen for flush opportunities (Server.terminate + Command.afterExecute + shutdown).
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
