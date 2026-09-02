<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher\Mongo;

use Cake\Log\Log;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Watcher\Watcher;

/**
 * Soft Crustum Mongo query-log watcher (Cake Log scopes).
 *
 * Analogue of SQL {@see \Crustum\Speculum\Watcher\QueryLogEngine} + LogWatcher
 * registration: listens for `mongoQueriesLog` / `mongo.database.queries`.
 */
class MongoQueryLogWatcher extends Watcher
{
    /**
     * Default Cake Log scopes for Crustum Mongo QueryLogger.
     *
     * @var list<string>
     */
    public const SCOPES = ['mongoQueriesLog', 'mongo.database.queries'];

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        if (!WatcherRegistry::isSoftAvailable(SoftFeature::CrustumMongo)) {
            return;
        }

        if (Log::getConfig('speculum_mongo_query_logs')) {
            Log::drop('speculum_mongo_query_logs');
        }

        $scopes = $this->options['scopes'] ?? self::SCOPES;
        if (!is_array($scopes) || $scopes === []) {
            $scopes = self::SCOPES;
        }

        Log::setConfig('speculum_mongo_query_logs', [
            'className' => MongoQueryLogEngine::class,
            'levels' => [],
            'scopes' => array_values($scopes),
        ]);
    }

    /**
     * Record a Mongo query-log entry (Cake Log path).
     *
     * @param string $query Encoded command / payload text.
     * @param float $time Duration in ms.
     * @param string|null $connection Connection name.
     * @param array<string, mixed> $context Extra context.
     * @return void
     */
    public function record(
        string $query,
        float $time,
        ?string $connection = null,
        array $context = [],
    ): void {
        if (!Speculum::isRecording()) {
            return;
        }

        if ($this->shouldIgnoreConnection($connection)) {
            return;
        }

        $query = trim($query);
        if ($query === '') {
            return;
        }

        $slow = $this->isSlowDuration($time);

        Speculum::recordEntry(EntryType::MongoQueryLog, IncomingEntry::make([
            'connection' => $connection,
            'query' => $query,
            'operation' => $context['operation'] ?? null,
            'database' => $context['database'] ?? null,
            'collection' => $context['collection'] ?? null,
            'time' => number_format($time, 2, '.', ''),
            'slow' => $slow,
            'hash' => md5($query),
            'source' => 'log',
        ])->tags($this->slowTags($time))->withFamilyHash(md5($query)));
    }

    /**
     * Whether the connection name is configured to be ignored.
     *
     * @param string|null $connection Connection name.
     * @return bool
     */
    protected function shouldIgnoreConnection(?string $connection): bool
    {
        if ($connection === null || $connection === '') {
            return false;
        }

        $ignored = array_values(array_map(strval(...), $this->options['ignore_connections'] ?? []));

        return in_array($connection, $ignored, true);
    }
}
