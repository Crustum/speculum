<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\Core\Configure;
use Cake\Log\Log;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\Mongo\CrustumMongoWatcher;
use Crustum\Speculum\Watcher\Mongo\MongoQueryLogEngine;
use Crustum\Speculum\Watcher\Mongo\MongoQueryLogWatcher;
use ReflectionClass;

/**
 * Crustum Mongo query / query-log watcher tests.
 */
class CrustumMongoWatcherTest extends TestCaseBase
{
    /**
     * @return void
     */
    protected function tearDown(): void
    {
        if (Log::getConfig('speculum_mongo_query_logs')) {
            Log::drop('speculum_mongo_query_logs');
        }

        CrustumMongoWatcher::resetWrapped();
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testSoftFeatureFollowsCrustumMongoDriver(): void
    {
        $this->assertSame(
            class_exists('Crustum\\Mongo\\Database\\Driver\\MongoDriver'),
            WatcherRegistry::isSoftAvailable(SoftFeature::CrustumMongo),
        );
    }

    /**
     * @return void
     */
    public function testRecordStoresMongoQueryEntry(): void
    {
        $watcher = new CrustumMongoWatcher(['enabled' => true, 'slow' => 50]);
        $watcher->record('{"operation":"find"}', 12.5, 'mongo', [
            'operation' => 'find',
            'database' => 'demo',
            'collection' => 'authors',
        ]);

        $this->assertCount(1, Speculum::$entriesQueue);
        $entry = Speculum::$entriesQueue[0];
        $this->assertSame(EntryType::MongoQuery->value, $entry->type);
        $this->assertSame('{"operation":"find"}', $entry->content['query']);
        $this->assertSame('mongo', $entry->content['connection']);
        $this->assertSame('find', $entry->content['operation']);
        $this->assertSame('driver', $entry->content['source']);
        $this->assertFalse($entry->content['slow']);
    }

    /**
     * @return void
     */
    public function testQueryLogWatcherRecordsMongoQueryLogEntry(): void
    {
        $watcher = new MongoQueryLogWatcher(['enabled' => true, 'slow' => 10]);
        $watcher->record('{"operation":"insert"}', 100.0, 'mongo', [
            'operation' => 'insert',
        ]);

        $this->assertCount(1, Speculum::$entriesQueue);
        $entry = Speculum::$entriesQueue[0];
        $this->assertSame(EntryType::MongoQueryLog->value, $entry->type);
        $this->assertSame('log', $entry->content['source']);
        $this->assertTrue($entry->content['slow']);
        $this->assertContains('slow', $entry->tags);
    }

    /**
     * @return void
     */
    public function testQueryLogEngineForwardsToWatcher(): void
    {
        Configure::write('Speculum.watchers.' . MongoQueryLogWatcher::class, [
            'enabled' => true,
            'slow' => 100,
        ]);

        $registry = new ReflectionClass(WatcherRegistry::class);
        $prop = $registry->getProperty('watchers');
        $prop->setValue(null, [MongoQueryLogWatcher::class]);

        $engine = new MongoQueryLogEngine(['scopes' => MongoQueryLogWatcher::SCOPES]);
        $engine->log('debug', '{"operation":"command"}', [
            'took' => 5.5,
            'connection' => 'mongo',
            'operation' => 'command',
            'database' => 'demo',
        ]);

        $this->assertCount(1, Speculum::$entriesQueue);
        $this->assertSame(EntryType::MongoQueryLog->value, Speculum::$entriesQueue[0]->type);
        $this->assertSame('5.50', Speculum::$entriesQueue[0]->content['time']);

        $prop->setValue(null, []);
        Configure::delete('Speculum.watchers.' . MongoQueryLogWatcher::class);
    }

    /**
     * @return void
     */
    public function testAvailableWatchersIncludesCrustumMongoTabsWhenEnabled(): void
    {
        Configure::write('Speculum.watchers.' . CrustumMongoWatcher::class, ['enabled' => true]);
        Configure::write('Speculum.watchers.' . MongoQueryLogWatcher::class, ['enabled' => true]);
        $available = WatcherRegistry::availableWatchers();

        if (class_exists('Crustum\\Mongo\\Database\\Driver\\MongoDriver')) {
            $this->assertContains('mongo-queries', $available);
            $this->assertContains('mongo-query-logs', $available);
        } else {
            $this->assertNotContains('mongo-queries', $available);
            $this->assertNotContains('mongo-query-logs', $available);
        }

        Configure::write('Speculum.watchers.' . CrustumMongoWatcher::class, ['enabled' => false]);
        Configure::write('Speculum.watchers.' . MongoQueryLogWatcher::class, ['enabled' => false]);
        $this->assertNotContains('mongo-queries', WatcherRegistry::availableWatchers());
        $this->assertNotContains('mongo-query-logs', WatcherRegistry::availableWatchers());

        Configure::delete('Speculum.watchers.' . CrustumMongoWatcher::class);
        Configure::delete('Speculum.watchers.' . MongoQueryLogWatcher::class);
    }
}
