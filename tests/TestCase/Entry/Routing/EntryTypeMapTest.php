<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Entry\Routing;

use Crustum\Speculum\Entry\Routing\EntryTypeMap;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Test\TestCase\TestCaseBase;

/**
 * EntryTypeMap lean path to type registry tests.
 */
class EntryTypeMapTest extends TestCaseBase
{
    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        WatcherRegistry::clearEntryResources();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        WatcherRegistry::clearEntryResources();

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testRegisterSingleType(): void
    {
        EntryTypeMap::register('queries', 'query');

        $this->assertSame('query', EntryTypeMap::type('queries'));
        $this->assertSame(['queries' => 'query'], EntryTypeMap::all());
    }

    /**
     * @return void
     */
    public function testRegisterMergesTypesByPath(): void
    {
        EntryTypeMap::register('authorization', 'authorization');
        EntryTypeMap::register('authorization', 'cakedc_auth');

        $type = EntryTypeMap::type('authorization');
        $this->assertIsArray($type);
        $this->assertContains('authorization', $type);
        $this->assertContains('cakedc_auth', $type);
    }

    /**
     * @return void
     */
    public function testTypeReturnsNullForUnknownPath(): void
    {
        $this->assertNull(EntryTypeMap::type('missing-' . uniqid()));
    }

    /**
     * @return void
     */
    public function testRegisterTrimsSlashes(): void
    {
        EntryTypeMap::register('/queries/', 'query');

        $this->assertSame('query', EntryTypeMap::type('queries'));
    }

    /**
     * @return void
     */
    public function testRegisterIgnoresEmptyPath(): void
    {
        EntryTypeMap::register('///', 'query');

        $this->assertSame([], EntryTypeMap::all());
    }

    /**
     * @return void
     */
    public function testUnregisterRemovesPath(): void
    {
        EntryTypeMap::register('queries', 'query');
        EntryTypeMap::unregister('queries');

        $this->assertNull(EntryTypeMap::type('queries'));
    }

    /**
     * @return void
     */
    public function testClearRemovesAll(): void
    {
        EntryTypeMap::register('queries', 'query');
        EntryTypeMap::register('logs', 'log');
        EntryTypeMap::clear();

        $this->assertSame([], EntryTypeMap::all());
    }
}
