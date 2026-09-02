<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher\Mongo;

use Cake\Core\Configure;
use Cake\Database\Log\LoggedQuery;
use Cake\Log\Engine\BaseLog;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use Stringable;

/**
 * Log engine that forwards Mongo query logs to MongoQueryLogWatcher.
 *
 * Analogue of {@see \Crustum\Speculum\Watcher\QueryLogEngine}.
 */
class MongoQueryLogEngine extends BaseLog
{
    /**
     * @inheritDoc
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!Speculum::isRecording() || !WatcherRegistry::has(MongoQueryLogWatcher::class)) {
            return;
        }

        $query = '';
        $time = 0.0;
        $connection = null;
        $extra = [];

        if (isset($context['query']) && $context['query'] instanceof LoggedQuery) {
            $logged = $context['query'];
            $serialized = $logged->jsonSerialize();
            $query = (string)($serialized['query'] ?? '');
            if ($query === '') {
                $query = (string)$logged;
            }

            $queryContext = $logged->getContext();
            $time = (float)($queryContext['took'] ?? 0);
            $connection = $logged->getConnectionName() ?: null;
            if ($connection === null && isset($queryContext['connection'])) {
                $connection = (string)$queryContext['connection'];
            }
        } elseif (isset($context['query']) && is_string($context['query']) && $context['query'] !== '') {
            $query = $context['query'];
            $time = (float)($context['took'] ?? $context['duration_ms'] ?? 0);
            $connection = isset($context['connection']) ? (string)$context['connection'] : null;
        } else {
            $query = (string)$message;
            $time = (float)($context['took'] ?? $context['duration_ms'] ?? 0);
            $connection = isset($context['connection']) ? (string)$context['connection'] : null;
        }

        if (isset($context['took']) && (float)$context['took'] > 0) {
            $time = (float)$context['took'];
        }

        if (isset($context['duration_ms']) && (float)$context['duration_ms'] > 0) {
            $time = (float)$context['duration_ms'];
        }

        if (($connection === null || $connection === '') && isset($context['connection'])) {
            $connection = (string)$context['connection'];
        }

        foreach (['operation', 'database', 'collection'] as $key) {
            if (isset($context[$key])) {
                $extra[$key] = $context[$key];
            }
        }

        $options = Configure::read('Speculum.watchers.' . MongoQueryLogWatcher::class, []);
        $watcher = new MongoQueryLogWatcher(is_array($options) ? $options : []);
        $watcher->record($query, $time, $connection, $extra);
    }
}
