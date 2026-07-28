<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\Database\Log\LoggedQuery;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\QueryLogEngine;
use Crustum\Speculum\Watcher\QueryWatcher;
use Crustum\Speculum\Watcher\SpeculumQueryLogger;

/**
 * Query watcher tests.
 */
class QueryWatcherTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testQueryWatcherSkipsDebugKitConnection(): void
    {
        $watcher = new QueryWatcher([
            'enabled' => true,
            'slow' => 1000,
            'ignore_connections' => ['debug_kit'],
        ]);
        $watcher->record(
            'SELECT * FROM panels WHERE request_id = :c0',
            1.0,
            'debug_kit',
            'sqlite',
        );

        $this->assertSame([], Speculum::$entriesQueue);
        $this->assertSame([], $this->loadSpeculumEntries());
    }

    /**
     * @return void
     */
    public function testQueryWatcherSkipsSpeculumStorageSql(): void
    {
        $watcher = new QueryWatcher([
            'enabled' => true,
            'slow' => 1000,
        ]);
        $watcher->record(
            'select count(*) as aggregate from speculum_entries',
            12.5,
            'test',
            'sqlite',
        );
        $watcher->record(
            'INSERT INTO "speculum_entries_tags" (entry_uuid, tag) VALUES (:c0, :c1)',
            1.0,
            'test',
            'postgres',
        );
        $watcher->record(
            'select tag from speculum_monitoring',
            0.5,
            'test',
            'sqlite',
        );

        $this->assertSame([], Speculum::$entriesQueue);
        $this->assertSame([], $this->loadSpeculumEntries());
    }

    /**
     * @return void
     */
    public function testQueryWatcherRegistersDatabaseQueries(): void
    {
        $this->registerWatcherClass(QueryWatcher::class);
        $watcher = new QueryWatcher([
            'enabled' => true,
            'slow' => 200,
        ]);
        $watcher->record(
            'select count(*) as aggregate from users',
            12.5,
            'test',
            'sqlite',
        );

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Query->value, $entry->type);
        $this->assertSame('select count(*) as aggregate from users', $entry->content['sql']);
        $this->assertSame('test', $entry->content['connection']);
        $this->assertSame('sqlite', $entry->content['driver']);
        $this->assertFalse($entry->content['slow']);
        $this->assertSame('12.50', $entry->content['time']);
        $this->assertStringNotContainsString('{connection}', $entry->content['sql']);
    }

    /**
     * @return void
     */
    public function testQueryWatcherCanTagSlowQueries(): void
    {
        $watcher = new QueryWatcher([
            'enabled' => true,
            'slow' => 0.2,
        ]);
        $sql = 'insert into users (email) values ' . implode(', ', array_fill(0, 50, "('x')"));
        $watcher->record($sql, 25.0, 'test', 'sqlite');

        $this->assertNotEmpty(Speculum::$entriesQueue);
        $this->assertContains('slow', Speculum::$entriesQueue[0]->tags);

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Query->value, $entry->type);
        $this->assertTrue($entry->content['slow']);
        $this->assertSame('test', $entry->content['connection']);
    }

    /**
     * @return void
     */
    public function testQueryWatcherNormalizesLoggerPrefix(): void
    {
        $watcher = new QueryWatcher(['enabled' => true, 'slow' => 1000]);
        $watcher->record(
            'connection={connection} role={role} duration={took} rows={numRows} SELECT id FROM users',
            5.0,
            'default',
            'mysql',
        );

        $entries = $this->loadSpeculumEntries();
        $this->assertSame('SELECT id FROM users', $entries[0]->content['sql']);
        $this->assertStringNotContainsString('{connection}', $entries[0]->content['sql']);
    }

    /**
     * @return void
     */
    public function testQueryLogEngineUsesContextQuery(): void
    {
        $this->registerWatcherClass(QueryWatcher::class);

        $engine = new QueryLogEngine(['scopes' => ['queriesLog']]);
        $engine->log('debug', 'connection={connection} role={role} duration={took} rows={numRows} SELECT 1', [
            'query' => 'SELECT id FROM users WHERE id = 1',
            'took' => 12.5,
            'connection' => 'default',
            'role' => 'write',
            'numRows' => 1,
        ]);

        $entries = $this->loadSpeculumEntries();
        $this->assertNotEmpty($entries);
        $this->assertSame('SELECT id FROM users WHERE id = 1', $entries[0]->content['sql']);
        $this->assertSame('12.50', $entries[0]->content['time']);
        $this->assertSame('default', $entries[0]->content['connection']);
    }

    /**
     * @return void
     */
    public function testQueryLogEngineRecordsLoggedQueryBindings(): void
    {
        $this->registerWatcherClass(QueryWatcher::class);

        $logged = new LoggedQuery();
        $logged->setContext([
            'query' => 'SELECT id FROM users WHERE id = :id',
            'params' => ['id' => 42],
            'took' => 3.5,
        ]);

        $engine = new QueryLogEngine(['scopes' => ['queriesLog']]);
        $engine->log('debug', (string)$logged, [
            'query' => $logged,
        ]);

        $entries = $this->loadSpeculumEntries();
        $this->assertNotEmpty($entries);
        $this->assertSame('SELECT id FROM users WHERE id = :id', $entries[0]->content['sql']);
        $this->assertSame(['id' => 42], $entries[0]->content['bindings']);
        $this->assertSame('3.50', $entries[0]->content['time']);
    }

    /**
     * @return void
     */
    public function testSpeculumQueryLoggerRecordsBindingsFromLoggedQuery(): void
    {
        $this->registerWatcherClass(QueryWatcher::class);

        $logged = new LoggedQuery();
        $logged->setContext([
            'query' => 'SELECT id FROM users WHERE id = :c0',
            'params' => ['c0' => 42],
            'took' => 2.5,
        ]);

        $logger = new SpeculumQueryLogger();
        $logger->log('debug', (string)$logged, ['query' => $logged]);

        $entries = $this->loadSpeculumEntries();
        $this->assertNotEmpty($entries);
        $this->assertSame('SELECT id FROM users WHERE id = :c0', $entries[0]->content['sql']);
        $this->assertSame(['c0' => 42], $entries[0]->content['bindings']);
        $this->assertSame('2.50', $entries[0]->content['time']);
    }
}
