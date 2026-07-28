<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Core\Configure;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use Cake\Database\Driver\Sqlite;
use Cake\Database\Driver\Sqlserver;
use Cake\Database\Log\LoggedQuery;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Engine\BaseLog;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use Stringable;
use Throwable;

/**
 * Log engine that forwards DB query logs to QueryWatcher.
 */
class QueryLogEngine extends BaseLog
{
    /**
     * @inheritDoc
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!Speculum::isRecording() || !WatcherRegistry::has(QueryWatcher::class)) {
            return;
        }

        $sql = '';
        $time = 0.0;
        $connection = null;
        $bindings = [];

        if (isset($context['query']) && $context['query'] instanceof LoggedQuery) {
            $logged = $context['query'];
            $serialized = $logged->jsonSerialize();
            $sql = (string)($serialized['query'] ?? '');
            if ($sql === '') {
                $sql = (string)$logged;
            }

            $bindings = is_array($serialized['params'] ?? null) ? $serialized['params'] : [];
            $queryContext = $logged->getContext();
            $time = (float)($queryContext['took'] ?? 0);
            $connection = $logged->getConnectionName() ?: null;
            if ($connection === null && isset($queryContext['connection'])) {
                $connection = (string)$queryContext['connection'];
            }
        } elseif (isset($context['query']) && is_string($context['query']) && $context['query'] !== '') {
            $sql = $context['query'];
            $time = (float)($context['took'] ?? 0);
            $connection = isset($context['connection']) ? (string)$context['connection'] : null;
            if (isset($context['params']) && is_array($context['params'])) {
                $bindings = $context['params'];
            } elseif (isset($context['bindings']) && is_array($context['bindings'])) {
                $bindings = $context['bindings'];
            }
        } else {
            $sql = (string)$message;
            $time = (float)($context['took'] ?? 0);
            $connection = isset($context['connection']) ? (string)$context['connection'] : null;
            if ($time <= 0.0 && preg_match('/took\s+([\d.]+)\s*ms/i', $sql, $matches)) {
                $time = (float)$matches[1];
            }

            if (isset($context['params']) && is_array($context['params'])) {
                $bindings = $context['params'];
            } elseif (isset($context['bindings']) && is_array($context['bindings'])) {
                $bindings = $context['bindings'];
            }
        }

        if (isset($context['took']) && (float)$context['took'] > 0) {
            $time = (float)$context['took'];
        }

        if (($connection === null || $connection === '') && isset($context['connection'])) {
            $connection = (string)$context['connection'];
        }

        $driver = $this->resolveDriverName($connection);

        $options = Configure::read('Speculum.watchers.' . QueryWatcher::class, []);
        $watcher = new QueryWatcher(is_array($options) ? $options : []);
        $watcher->record($sql, $time, $connection, $driver, $bindings);
    }

    /**
     * Resolve a SQL dialect name for the given connection.
     *
     * @param string|null $connectionName Connection config name.
     * @return string|null
     */
    protected function resolveDriverName(?string $connectionName): ?string
    {
        if ($connectionName === null || $connectionName === '') {
            return null;
        }

        try {
            $connection = ConnectionManager::get($connectionName);
            $driver = $connection->getDriver();

            return match (true) {
                $driver instanceof Postgres => 'postgresql',
                $driver instanceof Mysql => 'mysql',
                $driver instanceof Sqlite => 'sqlite',
                $driver instanceof Sqlserver => 'transactsql',
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }
}
