<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Entry;

use Crustum\Speculum\Entry\Presentation\GenericEntryPresentation;
use Crustum\Speculum\Entry\Presentation\PresentationRegistry;
use Crustum\Speculum\Entry\Presentation\QueryEntryPresentation;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Test\TestCase\TestCaseBase;

/**
 * Presentation registry tests.
 */
class PresentationRegistryTest extends TestCaseBase
{
    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        PresentationRegistry::reset();
        WatcherRegistry::registerDefaultEntryResources();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        PresentationRegistry::reset();

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testEveryEntryTypeResolves(): void
    {
        foreach (EntryType::cases() as $case) {
            $this->assertInstanceOf(
                GenericEntryPresentation::class,
                PresentationRegistry::for($case->value),
                "Type {$case->value} does not resolve.",
            );
        }
    }

    /**
     * @return void
     */
    public function testDedicatedTypesResolveToDedicatedPresentations(): void
    {
        $this->assertInstanceOf(
            QueryEntryPresentation::class,
            PresentationRegistry::for(EntryType::Query->value),
        );
    }

    /**
     * @return void
     */
    public function testRecordableTypesResolveToDedicatedPresentations(): void
    {
        $dedicated = [
            EntryType::Request->value,
            EntryType::Query->value,
            EntryType::Exception->value,
            EntryType::Job->value,
            EntryType::Cache->value,
            EntryType::Log->value,
            EntryType::Mail->value,
            EntryType::Event->value,
            EntryType::Command->value,
            EntryType::HttpClient->value,
            EntryType::Ai->value,
            EntryType::Authorization->value,
            EntryType::CakeDCAuth->value,
            EntryType::Batch->value,
            EntryType::BlazeCastDelivery->value,
            EntryType::BlazeCastMessage->value,
            EntryType::Broadcast->value,
            EntryType::Explorator->value,
            EntryType::Model->value,
            EntryType::Mongo->value,
            EntryType::MongoQuery->value,
            EntryType::MongoQueryLog->value,
            EntryType::Notification->value,
            EntryType::ScheduledTask->value,
            EntryType::VarDump->value,
            EntryType::View->value,
        ];

        foreach ($dedicated as $type) {
            $this->assertNotSame(
                GenericEntryPresentation::class,
                PresentationRegistry::for($type)::class,
                "Type {$type} falls back to generic.",
            );
        }
    }

    /**
     * @return void
     */
    public function testUnlistedTypesFallBackToGeneric(): void
    {
        $this->assertSame(GenericEntryPresentation::class, PresentationRegistry::for(EntryType::Redis->value)::class);
        $this->assertSame(
            GenericEntryPresentation::class,
            PresentationRegistry::for(EntryType::BlazeCastConnection->value)::class,
        );
    }

    /**
     * @return void
     */
    public function testValidTypesMatchEnum(): void
    {
        $this->assertSame(EntryType::all(), PresentationRegistry::validTypes());
    }

    /**
     * @return void
     */
    public function testResolveTypeAcceptsValuesAndResourcePaths(): void
    {
        $this->assertNull(PresentationRegistry::resolveType(null));
        $this->assertNull(PresentationRegistry::resolveType(''));
        $this->assertSame('query', PresentationRegistry::resolveType('query'));
        $this->assertSame('http_client', PresentationRegistry::resolveType('http-clients'));
        $this->assertNull(PresentationRegistry::resolveType('bogus'));
    }

    /**
     * @return void
     */
    public function testRegisterOverridesPresentation(): void
    {
        PresentationRegistry::for(EntryType::Mongo->value);
        PresentationRegistry::register(EntryType::Mongo->value, QueryEntryPresentation::class);

        $this->assertInstanceOf(
            QueryEntryPresentation::class,
            PresentationRegistry::for(EntryType::Mongo->value),
        );
    }
}
