<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\Core\Configure;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\MongoWatcher;

/**
 * MongoWatcher unit tests.
 */
class MongoWatcherTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testSoftFeatureFollowsMongodbExtension(): void
    {
        $this->assertSame(
            extension_loaded('mongodb'),
            WatcherRegistry::isSoftAvailable(SoftFeature::Mongo),
        );
    }

    /**
     * @return void
     */
    public function testRecordStoresMongoEntry(): void
    {
        $watcher = new MongoWatcher(['enabled' => true, 'slow' => 50]);
        $watcher->record('find', 12.5, 'zulucare_mongo', 'files', ['find' => 'files', 'filter' => []]);

        $this->assertCount(1, Speculum::$entriesQueue);
        $entry = Speculum::$entriesQueue[0];
        $this->assertSame(EntryType::Mongo->value, $entry->type);
        $this->assertSame('find', $entry->content['command']);
        $this->assertSame('zulucare_mongo', $entry->content['database']);
        $this->assertSame('files', $entry->content['collection']);
        $this->assertFalse($entry->content['slow']);
    }

    /**
     * @return void
     */
    public function testRecordMarksSlowAndIgnoresHello(): void
    {
        $watcher = new MongoWatcher([
            'slow' => 10,
            'ignore_commands' => ['hello'],
        ]);
        $watcher->record('hello', 100.0, 'admin', null, []);
        $this->assertSame([], Speculum::$entriesQueue);

        $watcher->record('insert', 100.0, 'db', 'col', ['insert' => 'col']);
        $this->assertCount(1, Speculum::$entriesQueue);
        $this->assertTrue(Speculum::$entriesQueue[0]->content['slow']);
        $this->assertContains('slow', Speculum::$entriesQueue[0]->tags);
    }

    /**
     * @return void
     */
    public function testAvailableWatchersIncludesMongoOnlyWhenEnabledAndExtensionLoaded(): void
    {
        Configure::write('Speculum.watchers.' . MongoWatcher::class, ['enabled' => true]);
        $available = WatcherRegistry::availableWatchers();
        if (extension_loaded('mongodb')) {
            $this->assertContains('mongo', $available);
        } else {
            $this->assertNotContains('mongo', $available);
        }

        Configure::delete('Speculum.watchers.' . MongoWatcher::class);
        $this->assertNotContains('mongo', WatcherRegistry::availableWatchers());

        Configure::write('Speculum.watchers.' . MongoWatcher::class, ['enabled' => false]);
        $this->assertNotContains('mongo', WatcherRegistry::availableWatchers());
        Configure::delete('Speculum.watchers.' . MongoWatcher::class);
    }
}
