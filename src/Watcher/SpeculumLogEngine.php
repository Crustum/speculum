<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Core\Configure;
use Cake\Log\Engine\BaseLog;
use Crustum\Speculum\Registry\WatcherRegistry;
use Stringable;

/**
 * PSR log engine forwarding to LogWatcher.
 */
class SpeculumLogEngine extends BaseLog
{
    /**
     * @inheritDoc
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!WatcherRegistry::has(LogWatcher::class)) {
            return;
        }

        $options = Configure::read('Speculum.watchers.' . LogWatcher::class, []);
        $watcher = new LogWatcher(is_array($options) ? $options : []);
        $watcher->record((string)$level, (string)$message, $context);
    }
}
