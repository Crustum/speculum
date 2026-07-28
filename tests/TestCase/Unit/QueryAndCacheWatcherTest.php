<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Unit;

use Cake\Cache\Engine\ArrayEngine;
use Cake\Cache\Event\CacheAfterGetEvent;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\CacheWatcher;
use Crustum\Speculum\Watcher\QueryLogEngine;
use Crustum\Speculum\Watcher\QueryWatcher;

/**
 * Query and cache watcher payload shape tests.
 */
class QueryAndCacheWatcherTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testQueryLogEngineUsesContextQueryNotMessagePrefix(): void
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

        Speculum::store($this->repository);
        $entries = $this->repository->get(EntryType::Query->value, new EntryQueryOptions());

        $this->assertNotEmpty($entries);
        $this->assertSame('SELECT id FROM users WHERE id = 1', $entries[0]->content['sql']);
        $this->assertSame('12.50', $entries[0]->content['time']);
        $this->assertSame('default', $entries[0]->content['connection']);
        $this->assertStringNotContainsString('{connection}', $entries[0]->content['sql']);
    }

    /**
     * @return void
     */
    public function testNormalizeSqlStripsQueryLoggerPrefix(): void
    {
        $watcher = new QueryWatcher(['enabled' => true]);
        $normalized = $watcher->normalizeSql(
            'connection={connection} role={role} duration={took} rows={numRows} SELECT * FROM tags',
        );

        $this->assertSame('SELECT * FROM tags', $normalized);
    }

    /**
     * @return void
     */
    public function testCacheWatcherReadsKeyFromTypedEvent(): void
    {
        $watcher = new CacheWatcher(['enabled' => true]);
        $engine = new ArrayEngine();
        $engine->init(['prefix' => '']);

        $event = new CacheAfterGetEvent(CacheAfterGetEvent::NAME, $engine, [
            'key' => 'app_settings',
            'value' => ['a' => 1],
            'success' => true,
        ]);

        $this->assertSame([], $event->getData());
        $watcher->recordFromEvent($event);

        Speculum::store($this->repository);
        $entries = $this->repository->get(EntryType::Cache->value, new EntryQueryOptions());

        $this->assertNotEmpty($entries);
        $this->assertSame('app_settings', $entries[0]->content['key']);
        $this->assertSame('hit', $entries[0]->content['type']);
    }
}
