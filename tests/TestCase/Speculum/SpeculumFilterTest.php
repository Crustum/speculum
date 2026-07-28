<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Speculum;

use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Crustum\Speculum\Test\TestCase\TestCaseBase;

/**
 * Speculum filter/tag orchestration coverage.
 */
class SpeculumFilterTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testFilterRejectsEntries(): void
    {
        Speculum::filter(fn(IncomingEntry $entry): bool => ($entry->content['keep'] ?? false) === true);

        Speculum::recordEntry(EntryType::Request, IncomingEntry::make(['keep' => false, 'uri' => '/no']));
        Speculum::recordEntry(EntryType::Request, IncomingEntry::make(['keep' => true, 'uri' => '/yes']));

        $this->assertCount(1, Speculum::$entriesQueue);
        $this->assertSame('/yes', Speculum::$entriesQueue[0]->content['uri']);
    }

    /**
     * @return void
     */
    public function testTagCallbackApplied(): void
    {
        Speculum::tag(fn(IncomingEntry $entry): array => ['tagged']);

        Speculum::recordEntry(EntryType::Log, IncomingEntry::make([
            'level' => 'error',
            'message' => 'hello',
        ]));

        $this->assertContains('tagged', Speculum::$entriesQueue[0]->tags);
        $this->assertSame(EntryType::Log->value, Speculum::$entriesQueue[0]->type);
    }

    /**
     * @return void
     */
    public function testStoreFlushesQueue(): void
    {
        Speculum::recordEntry(EntryType::Cache, IncomingEntry::make([
            'type' => 'hit',
            'key' => 'demo',
            'value' => 1,
        ]));

        Speculum::store($this->repository);
        $this->assertSame([], Speculum::$entriesQueue);

        $entries = $this->repository->get(EntryType::Cache->value, new EntryQueryOptions());
        $this->assertCount(1, $entries);
    }
}
