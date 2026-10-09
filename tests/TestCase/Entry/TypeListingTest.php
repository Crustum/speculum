<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Entry;

use Crustum\Speculum\Entry\Inspection\EntryListing;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Test\TestCase\TestCaseBase;

/**
 * Entry type listing tests (no storage touch).
 */
class TypeListingTest extends TestCaseBase
{
    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        WatcherRegistry::registerDefaultEntryResources();
    }

    /**
     * @return void
     */
    public function testTypesCoversEveryEntryType(): void
    {
        $payload = $this->listing()->types();

        $this->assertSame(['Type', 'Resource', 'Description'], $payload['headers']);

        $values = array_column($payload['types'], 'type');
        foreach (EntryType::cases() as $case) {
            $this->assertContains($case->value, $values);
        }
    }

    /**
     * @return void
     */
    public function testTypesCarriesResourceAndDescription(): void
    {
        $byType = [];
        foreach ($this->listing()->types()['types'] as $row) {
            $byType[$row['type']] = $row;
        }

        $this->assertSame('requests', $byType[EntryType::Request->value]['resource']);
        $this->assertSame(
            'HTTP requests with response status and duration.',
            $byType[EntryType::Request->value]['description'],
        );
        $this->assertSame(
            'AI agent and tool runs with usage.',
            $byType[EntryType::Ai->value]['description'],
        );
        $this->assertSame('', $byType[EntryType::Redis->value]['description']);
    }

    /**
     * Build the listing service.
     *
     * @return \Crustum\Speculum\Entry\Inspection\EntryListing
     */
    protected function listing(): EntryListing
    {
        return new EntryListing($this->repository);
    }
}
