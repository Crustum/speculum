<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\CacheWatcher;

/**
 * Cache watcher tests.
 */
class CacheWatcherTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testCacheWatcherRegistersMissEntries(): void
    {
        $watcher = new CacheWatcher(['enabled' => true]);
        $watcher->recordTyped('miss', 'empty-key');

        $entries = $this->loadSpeculumEntries();
        $this->assertNotEmpty($entries);
        $entry = $entries[0];

        $this->assertSame(EntryType::Cache->value, $entry->type);
        $this->assertSame('miss', $entry->content['type']);
        $this->assertSame('empty-key', $entry->content['key']);
    }

    /**
     * @return void
     */
    public function testCacheWatcherRegistersStoreEntries(): void
    {
        $watcher = new CacheWatcher(['enabled' => true]);
        $watcher->recordTyped('set', 'my-key', 'cake', 1);

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Cache->value, $entry->type);
        $this->assertSame('set', $entry->content['type']);
        $this->assertSame('my-key', $entry->content['key']);
        $this->assertSame('cake', $entry->content['value']);
    }

    /**
     * @return void
     */
    public function testCacheWatcherRegistersHitEntries(): void
    {
        $watcher = new CacheWatcher(['enabled' => true]);
        Speculum::withoutRecording(function () use ($watcher): void {
            $watcher->recordTyped('set', 'app-cache', 'cake', 1);
        });

        $watcher->recordTyped('hit', 'app-cache', 'cake');

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $entry = $entries[0];

        $this->assertSame(EntryType::Cache->value, $entry->type);
        $this->assertSame('hit', $entry->content['type']);
        $this->assertSame('app-cache', $entry->content['key']);
        $this->assertSame('cake', $entry->content['value']);
    }

    /**
     * @return void
     */
    public function testCacheWatcherRegistersForgetEntries(): void
    {
        $watcher = new CacheWatcher(['enabled' => true]);
        Speculum::withoutRecording(function () use ($watcher): void {
            $watcher->recordTyped('set', 'outdated', 'value', 1);
        });

        $watcher->recordTyped('forget', 'outdated');

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Cache->value, $entry->type);
        $this->assertSame('forget', $entry->content['type']);
        $this->assertSame('outdated', $entry->content['key']);
    }

    /**
     * @return void
     */
    public function testCacheWatcherHidesHiddenValuesWhenSet(): void
    {
        $watcher = new CacheWatcher([
            'enabled' => true,
            'hidden' => ['my-hidden-value-key'],
        ]);
        $watcher->recordTyped('set', 'my-hidden-value-key', 'cake', 1);

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Cache->value, $entry->type);
        $this->assertSame('set', $entry->content['type']);
        $this->assertSame('my-hidden-value-key', $entry->content['key']);
        $this->assertSame('(REDACTED)', $entry->content['value']);
    }

    /**
     * @return void
     */
    public function testCacheWatcherHidesHiddenValuesWhenRetrieved(): void
    {
        $watcher = new CacheWatcher([
            'enabled' => true,
            'hidden' => ['my-hidden-value-key'],
        ]);
        Speculum::withoutRecording(function () use ($watcher): void {
            $watcher->recordTyped('set', 'my-hidden-value-key', 'cake', 1);
        });

        $watcher->recordTyped('hit', 'my-hidden-value-key', 'cake');

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Cache->value, $entry->type);
        $this->assertSame('hit', $entry->content['type']);
        $this->assertSame('my-hidden-value-key', $entry->content['key']);
        $this->assertSame('(REDACTED)', $entry->content['value']);
    }

    /**
     * @return void
     */
    public function testCacheWatcherSkipsRecordingIgnoredCacheKeys(): void
    {
        $watcher = new CacheWatcher([
            'enabled' => true,
            'ignore' => [
                'cake:pulse:*',
                'ignored-key',
            ],
        ]);
        $watcher->recordTyped('set', 'ignored-key', 'cake');
        $watcher->recordTyped('set', 'cake:pulse:restart', 'cake');
        $watcher->recordTyped('set', 'my-key', 'cake');

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $entry = $entries[0];

        $this->assertSame(EntryType::Cache->value, $entry->type);
        $this->assertSame('set', $entry->content['type']);
        $this->assertSame('my-key', $entry->content['key']);
        $this->assertSame('cake', $entry->content['value']);
    }

    /**
     * @return void
     */
    public function testCacheWatcherSkipsEmptyAndSpeculumPrefixedKeys(): void
    {
        $watcher = new CacheWatcher([
            'enabled' => true,
            'ignore' => ['rhythm*'],
        ]);
        $watcher->recordTyped('hit', '', 'x');
        $watcher->recordTyped('hit', 'speculum:pause-recording', true);
        $watcher->recordTyped('miss', 'cake_speculum%3Apause-recording');
        $watcher->recordTyped(
            'set',
            'rhythm-widget-Crustum_Rhythm_Widget_SlowJobsWidget-slow_jobs_60_sort_slowest',
            ['rows' => []],
        );
        $watcher->recordTyped('miss', 'rhythm-widget-Crustum_Rhythm_Widget_QueuesWidget-queues_60');
        $watcher->recordTyped('hit', 'my%3Aencoded-key', 1);
        $watcher->recordTyped('hit', 'visible-key', 1);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(2, $entries);
        $keys = array_map(static fn($entry): string => (string)$entry->content['key'], $entries);
        sort($keys);
        $this->assertSame(['my:encoded-key', 'visible-key'], $keys);
    }

    /**
     * @return void
     */
    public function testCacheWatcherSkipsCakeFrameworkCoreKeysByDefault(): void
    {
        $watcher = new CacheWatcher(['enabled' => true]);
        $watcher->recordTyped('hit', 'myapp_cake_core_translations.default.en_US');
        $watcher->recordTyped('hit', 'myapp_cake_model_default_notifications');
        $watcher->recordTyped('hit', 'myapp_cake_routes_routeCollection');
        $watcher->recordTyped('set', 'session_a8e54faf4299def8b41d286e70ec9a33', 'Auth|…');
        $watcher->recordTyped('hit', 'session_a8e54faf4299def8b41d286e70ec9a33', 'Auth|…');
        $watcher->recordTyped('hit', 'app_feature_flags');

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('app_feature_flags', $entries[0]->content['key']);
    }
}
