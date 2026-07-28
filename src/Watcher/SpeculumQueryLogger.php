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
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;
use Throwable;

/**
 * Driver logger decorator that records LoggedQuery bindings before Cake QueryLogger strips them.
 */
class SpeculumQueryLogger extends AbstractLogger
{
    /**
     * Wrap an optional inner driver logger.
     *
     * @param \Psr\Log\LoggerInterface|null $logger Inner logger (Rhythm, QueryLogger, …).
     */
    public function __construct(
        protected ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Return the decorated inner logger.
     *
     * @return \Psr\Log\LoggerInterface|null
     */
    public function getInnerLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @inheritDoc
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $query = $context['query'] ?? null;
        if ($query instanceof LoggedQuery) {
            $this->recordLoggedQuery($query);
        }

        if ($this->logger instanceof LoggerInterface) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * Record a LoggedQuery into Speculum.
     *
     * @param \Cake\Database\Log\LoggedQuery $logged Logged query.
     * @return void
     */
    protected function recordLoggedQuery(LoggedQuery $logged): void
    {
        if (!Speculum::isRecording() || !WatcherRegistry::has(QueryWatcher::class)) {
            return;
        }

        $serialized = $logged->jsonSerialize();
        $sql = (string)($serialized['query'] ?? '');
        if ($sql === '') {
            $sql = (string)$logged;
        }

        $bindings = is_array($serialized['params'] ?? null) ? $serialized['params'] : [];
        $queryContext = $logged->getContext();
        $time = (float)($queryContext['took'] ?? $serialized['took'] ?? 0);
        $connection = $logged->getConnectionName();
        if ($connection === '' && isset($queryContext['connection'])) {
            $connection = (string)$queryContext['connection'];
        }

        if ($connection === '') {
            $connection = null;
        }

        $options = Configure::read('Speculum.watchers.' . QueryWatcher::class, []);
        $watcher = new QueryWatcher(is_array($options) ? $options : []);
        $watcher->record($sql, $time, $connection, $this->resolveDriverName($connection), $bindings);
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
