<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\ORM\Table;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\SearchesWatcher;

/**
 * Explorator watcher tests (soft feature; event stub without Explorator package).
 */
class SearchesWatcherTest extends TestCaseBase
{
    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->markSoftPluginLoaded('Crustum/Explorator');
        Configure::write('Speculum.watchers.' . SearchesWatcher::class, ['enabled' => true]);
    }

    /**
     * @return void
     */
    public function testSoftFeatureFollowsExploratorPlugin(): void
    {
        $this->assertTrue(WatcherRegistry::isSoftAvailable(SoftFeature::Explorator));
        $this->assertContains('searches', WatcherRegistry::availableWatchers());
    }

    /**
     * @return void
     */
    public function testRecordStoresSearchEntry(): void
    {
        $watcher = new SearchesWatcher([
            'enabled' => true,
            'slow' => 100,
        ]);
        $watcher->record([
            'query' => 'admiral',
            'index' => 'users',
            'engine' => 'MeilisearchEngine',
            'hits' => 3,
            'duration_ms' => 12.5,
            'operation' => 'search',
            'table' => 'users',
        ]);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(EntryType::Explorator->value, $entries[0]->type);
        $this->assertSame('admiral', $entries[0]->content['query']);
        $this->assertSame(3, $entries[0]->content['hits']);
        $this->assertSame(12.5, $entries[0]->content['duration_ms']);
        $this->assertSame('12.50', $entries[0]->content['time']);
        $this->assertFalse($entries[0]->content['slow']);
        $this->assertSame('MeilisearchEngine', $entries[0]->content['engine']);
        $this->assertSame('users', $entries[0]->content['index']);
        $this->assertSame('users', $entries[0]->content['table']);
        $this->assertNotNull($entries[0]->familyHash);
        $this->assertArrayNotHasKey('request', $entries[0]->content);
        $this->assertArrayNotHasKey('response', $entries[0]->content);
    }

    /**
     * @return void
     */
    public function testRecordStoresRequestAndResponseWhenEnabled(): void
    {
        $watcher = new SearchesWatcher([
            'enabled' => true,
            'slow' => 100,
            'request' => true,
            'response' => true,
        ]);
        $watcher->record([
            'query' => 'admiral',
            'index' => 'users',
            'engine' => 'MeilisearchEngine',
            'hits' => 3,
            'duration_ms' => 12.5,
            'operation' => 'search',
            'table' => 'users',
            'request' => ['query' => 'admiral', 'wheres' => []],
            'response' => ['hits' => 3, 'ids' => [1, 2]],
        ]);

        $this->assertCount(1, Speculum::$entriesQueue);
        $this->assertSame(['query' => 'admiral', 'wheres' => []], Speculum::$entriesQueue[0]->content['request']);
        $this->assertSame(['hits' => 3, 'ids' => [1, 2]], Speculum::$entriesQueue[0]->content['response']);
    }

    /**
     * @return void
     */
    public function testRecordOmitsRequestAndResponseWhenDisabled(): void
    {
        $watcher = new SearchesWatcher([
            'enabled' => true,
            'slow' => 100,
            'request' => false,
            'response' => false,
        ]);
        $watcher->record([
            'query' => 'admiral',
            'index' => 'users',
            'engine' => 'MeilisearchEngine',
            'hits' => 3,
            'duration_ms' => 12.5,
            'operation' => 'search',
            'request' => ['query' => 'admiral'],
            'response' => ['hits' => 3],
        ]);

        $this->assertCount(1, Speculum::$entriesQueue);
        $this->assertArrayNotHasKey('request', Speculum::$entriesQueue[0]->content);
        $this->assertArrayNotHasKey('response', Speculum::$entriesQueue[0]->content);
    }

    /**
     * @return void
     */
    public function testRecordMarksSlowSearches(): void
    {
        $watcher = new SearchesWatcher([
            'enabled' => true,
            'slow' => 50,
        ]);
        $watcher->record([
            'query' => 'slow',
            'engine' => 'DatabaseEngine',
            'hits' => 0,
            'duration_ms' => 75.0,
            'operation' => 'search',
        ]);

        $this->assertCount(1, Speculum::$entriesQueue);
        $this->assertTrue(Speculum::$entriesQueue[0]->content['slow']);
        $this->assertContains('slow', Speculum::$entriesQueue[0]->tags);
    }

    /**
     * @return void
     */
    public function testRegisterRecordsExploratorSearchPerformedEvent(): void
    {
        $watcher = new SearchesWatcher([
            'enabled' => true,
            'slow' => 100,
        ]);
        $watcher->register();

        $table = $this->createStub(Table::class);
        $table->method('getTable')->willReturn('articles');

        EventManager::instance()->dispatch(new Event(SearchesWatcher::EVENT_NAME, $table, [
            'query' => 'cakephp',
            'index' => 'articles',
            'engine' => 'TypesenseEngine',
            'hits' => 10,
            'duration_ms' => 8.25,
            'operation' => 'paginate',
            'page' => 2,
            'per_page' => 15,
        ]));

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(EntryType::Explorator->value, $entries[0]->type);
        $this->assertSame('cakephp', $entries[0]->content['query']);
        $this->assertSame('articles', $entries[0]->content['table']);
        $this->assertSame('paginate', $entries[0]->content['operation']);
        $this->assertSame(2, $entries[0]->content['page']);
        $this->assertSame(15, $entries[0]->content['per_page']);
    }

    /**
     * @return void
     */
    public function testRegisterRecordsExploratorIndexWritePerformedEvent(): void
    {
        $watcher = new SearchesWatcher([
            'enabled' => true,
            'slow' => 100,
        ]);
        $watcher->register();

        $table = $this->createStub(Table::class);
        $table->method('getTable')->willReturn('doc_chunks');

        EventManager::instance()->dispatch(new Event(SearchesWatcher::WRITE_EVENT_NAME, $table, [
            'operation' => 'update',
            'count' => 4,
            'index' => 'doc_chunks',
            'engine' => 'MeilisearchEngine',
            'duration_ms' => 22.5,
            'request' => ['source' => 'DocChunks', 'ids' => [10, 11]],
            'response' => ['operation' => 'update', 'count' => 4, 'index' => 'doc_chunks'],
        ]));

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(EntryType::Explorator->value, $entries[0]->type);
        $this->assertSame('update', $entries[0]->content['operation']);
        $this->assertSame(4, $entries[0]->content['count']);
        $this->assertNull($entries[0]->content['hits']);
        $this->assertSame('doc_chunks', $entries[0]->content['index']);
        $this->assertSame('doc_chunks', $entries[0]->content['table']);
        $this->assertSame('update · doc_chunks', $entries[0]->content['query']);
        $this->assertSame('22.50', $entries[0]->content['time']);
        $this->assertNull($entries[0]->familyHash);
            $this->assertSame('DocChunks', $entries[0]->content['request']['source']);
            $this->assertSame([10, 11], $entries[0]->content['request']['ids']);
        $this->assertSame('update', $entries[0]->content['response']['operation']);
        $this->assertSame(4, $entries[0]->content['response']['count']);
        $this->assertSame('doc_chunks', $entries[0]->content['response']['index']);
    }

    /**
     * @return void
     */
    public function testIndexWritesDoNotShareFamilyHash(): void
    {
        $watcher = new SearchesWatcher([
            'enabled' => true,
            'slow' => 100,
        ]);

        $watcher->record([
            'query' => 'update · doc_chunks',
            'index' => 'doc_chunks',
            'engine' => 'MeilisearchEngine',
            'count' => 1,
            'duration_ms' => 10.0,
            'operation' => 'update',
            'table' => 'doc_chunks',
        ]);
        $watcher->record([
            'query' => 'update · doc_chunks',
            'index' => 'doc_chunks',
            'engine' => 'MeilisearchEngine',
            'count' => 1,
            'duration_ms' => 11.0,
            'operation' => 'update',
            'table' => 'doc_chunks',
        ]);

        $this->assertCount(2, Speculum::$entriesQueue);
        $this->assertNull(Speculum::$entriesQueue[0]->familyHash());
        $this->assertNull(Speculum::$entriesQueue[1]->familyHash());
    }

    /**
     * @return void
     */
    public function testAvailableWatchersOmitsExploratorWithoutPlugin(): void
    {
        $this->assertContains('searches', WatcherRegistry::availableWatchers());

        Plugin::getCollection()->remove('Crustum/Explorator');
        $this->softPluginsLoaded = array_values(array_filter(
            $this->softPluginsLoaded,
            static fn(string $name): bool => $name !== 'Crustum/Explorator',
        ));

        $this->assertFalse(WatcherRegistry::isSoftAvailable(SoftFeature::Explorator));
        $this->assertNotContains('searches', WatcherRegistry::availableWatchers());
    }
}
