<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Entry;

use Crustum\Speculum\Entry\Inspection\EntryDetail;
use Crustum\Speculum\Entry\Inspection\EntryInspectionException;
use Crustum\Speculum\Entry\Inspection\EntryListing;
use Crustum\Speculum\Entry\Presentation\PresentationRegistry;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Test\TestCase\TestCaseBase;

/**
 * Entry inspection service tests.
 */
class EntryInspectionTest extends TestCaseBase
{
    use CreatesSpeculumEntriesTrait;

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
    public function testListWithoutTypeUsesGenericTable(): void
    {
        $this->createRequest(['uri' => '/users']);
        $this->createQuery(['sql' => 'select * from users']);

        $payload = (new EntryListing($this->repository))->list(null);

        $this->assertCount(2, $payload['entries']);
        $this->assertSame(['UUID', 'Type', 'Summary', 'Created'], $payload['headers']);
        $this->assertCount(2, $payload['rows']);
        $this->assertSame(2, $payload['pagination']['count']);
        $this->assertNull($payload['pagination']['nextBefore']);
        $this->assertArrayHasKey('id', $payload['entries'][0]);
        $this->assertArrayHasKey('batch_id', $payload['entries'][0]);
        $this->assertArrayHasKey('content', $payload['entries'][0]);
    }

    /**
     * @return void
     */
    public function testListWithTypeUsesDedicatedTable(): void
    {
        $this->createRequest(['uri' => '/users']);
        $this->createQuery(['sql' => 'select * from users']);

        $payload = (new EntryListing($this->repository))->list('query');

        $this->assertCount(1, $payload['entries']);
        $this->assertSame('query', $payload['entries'][0]['type']);
        $this->assertSame(['UUID', 'SQL', 'Time', 'Slow', 'Connection', 'Created'], $payload['headers']);
        $this->assertStringContainsString('select * from users', $payload['rows'][0][1]);
    }

    /**
     * @return void
     */
    public function testListAcceptsResourcePathAsType(): void
    {
        $this->createQuery(['sql' => 'select 1']);

        $payload = (new EntryListing($this->repository))->list('queries');

        $this->assertCount(1, $payload['entries']);
    }

    /**
     * @return void
     */
    public function testListPaginatesWithBeforeCursor(): void
    {
        $this->createRequest(['uri' => '/one']);
        $this->createRequest(['uri' => '/two']);
        $this->createRequest(['uri' => '/three']);

        $listing = new EntryListing($this->repository);
        $first = $listing->list('request', ['limit' => 2]);

        $this->assertCount(2, $first['entries']);
        $this->assertNotNull($first['pagination']['nextBefore']);

        $second = $listing->list('request', ['limit' => 2, 'before' => $first['pagination']['nextBefore']]);

        $this->assertCount(1, $second['entries']);
        $this->assertNull($second['pagination']['nextBefore']);
    }

    /**
     * @return void
     */
    public function testListRejectsInvalidType(): void
    {
        $this->expectException(EntryInspectionException::class);
        $this->expectExceptionMessage('Invalid entry type: bogus');

        (new EntryListing($this->repository))->list('bogus');
    }

    /**
     * @return void
     */
    public function testListRejectsBadLimit(): void
    {
        $this->expectException(EntryInspectionException::class);
        $this->expectExceptionMessage('positive integer');

        (new EntryListing($this->repository))->list(null, ['limit' => 'zero']);
    }

    /**
     * @return void
     */
    public function testListFiltersByTag(): void
    {
        $this->createRequest(['uri' => '/mine'], tags: ['Auth:42']);
        $this->createRequest(['uri' => '/other']);

        $payload = (new EntryListing($this->repository))->list('request', ['tag' => 'Auth:42']);

        $this->assertCount(1, $payload['entries']);
        $this->assertSame('/mine', $payload['entries'][0]['content']['uri']);
    }

    /**
     * @return void
     */
    public function testShowEntryWithBatchContext(): void
    {
        $batchId = '11111111-1111-1111-1111-111111111111';
        $uuid = $this->createRequest(['uri' => '/users'], $batchId);
        $this->createQuery(['sql' => 'select * from users'], $batchId);
        $this->createQuery(['sql' => 'select * from orders'], $batchId);

        $payload = (new EntryDetail($this->repository))->show($uuid);

        $this->assertSame($uuid, $payload['entry']['id']);
        $this->assertSame($batchId, $payload['batchId']);
        $this->assertCount(2, $payload['batch']);
        $this->assertSame('Request', $payload['detail']['label']);
        $this->assertSame(
            ['#', 'UUID', 'Time', 'SQL', 'Source', 'Flags'],
            $payload['batchGroups']['queries']['headers'],
        );
        $this->assertCount(2, $payload['batchGroups']['queries']['rows']);
        $this->assertSame(
            ['total' => 2, 'time' => 3.0, 'slow' => 0, 'duplicateGroups' => 0],
            $payload['batchGroups']['queries']['stats'],
        );
        $this->assertSame([], $payload['requestedTypes']);
    }

    /**
     * @return void
     */
    public function testShowLatestShortcuts(): void
    {
        $this->createRequest(['uri' => '/old']);
        $newUuid = $this->createRequest(['uri' => '/new']);
        $queryUuid = $this->createQuery(['sql' => 'select 1']);

        $detail = new EntryDetail($this->repository);

        $this->assertSame($queryUuid, $detail->show('latest')['entry']['id']);
        $this->assertSame($newUuid, $detail->show('latest:request')['entry']['id']);

        $latestQuery = $detail->show('latest:query');
        $this->assertSame('query', $latestQuery['entry']['type']);
    }

    /**
     * @return void
     */
    public function testShowMissingEntryThrows(): void
    {
        $this->expectException(EntryInspectionException::class);
        $this->expectExceptionMessage('Entry not found');

        (new EntryDetail($this->repository))->show('00000000-0000-0000-0000-000000000000');
    }

    /**
     * @return void
     */
    public function testShowFiltersBatchByType(): void
    {
        $batchId = '22222222-2222-2222-2222-222222222222';
        $uuid = $this->createRequest(['uri' => '/users'], $batchId);
        $this->createQuery(['sql' => 'select 1'], $batchId);
        $this->createLog(['message' => 'hello'], $batchId);

        $payload = (new EntryDetail($this->repository))->show($uuid, 'query');

        $this->assertCount(1, $payload['batch']);
        $this->assertSame('query', $payload['batch'][0]['type']);
        $this->assertSame(['query'], $payload['requestedTypes']);
        $this->assertSame([], $payload['batchGroups']['logs']['rows']);
    }

    /**
     * @return void
     */
    public function testShowFlagsRepeatedAndSlowQueries(): void
    {
        $batchId = '33333333-3333-3333-3333-333333333333';
        $uuid = $this->createRequest(['uri' => '/users'], $batchId);
        $dupA = $this->createQuery(['sql' => 'select * from users where id = 1', 'slow' => false], $batchId);
        $dupB = $this->createQuery(['sql' => 'select * from users where id = 1', 'slow' => true], $batchId);
        $this->createQuery(['sql' => 'select * from orders', 'slow' => false], $batchId);

        $payload = (new EntryDetail($this->repository))->show($uuid);
        $queries = $payload['batchGroups']['queries'];

        $this->assertSame('DUP', $queries['rows'][0][5]);
        $this->assertSame('DUP, SLOW', $queries['rows'][1][5]);
        $this->assertSame('', $queries['rows'][2][5]);
        $this->assertSame(substr($dupA, 0, 8), $queries['rows'][0][1]);
        $this->assertSame(substr($dupB, 0, 8), $queries['rows'][1][1]);
        $this->assertSame(1, $queries['stats']['duplicateGroups']);
        $this->assertSame(1, $queries['stats']['slow']);
    }

    /**
     * @return void
     */
    public function testShowComputesCacheStats(): void
    {
        $batchId = '44444444-4444-4444-4444-444444444444';
        $uuid = $this->createRequest(['uri' => '/users'], $batchId);
        $this->createCache(['type' => 'hit', 'key' => 'a'], $batchId);
        $this->createCache(['type' => 'missed', 'key' => 'b'], $batchId);
        $this->createCache(['type' => 'set', 'key' => 'c'], $batchId);

        $payload = (new EntryDetail($this->repository))->show($uuid);
        $cache = $payload['batchGroups']['cache'];
        $stats = $cache['stats'];

        $this->assertSame(1, $stats['hits']);
        $this->assertSame(1, $stats['misses']);
        $this->assertSame(0.5, $stats['rate']);
        $this->assertSame(['Action', 'Key'], $cache['headers']);
        $this->assertCount(3, $cache['rows']);
        $this->assertSame(['hit', 'a'], $cache['rows'][0]);
    }

    /**
     * @return void
     */
    public function testShowExceptionDetail(): void
    {
        $uuid = $this->createException(['class' => 'App\\Error\\DemoException', 'message' => 'Boom']);

        $payload = (new EntryDetail($this->repository))->show($uuid);

        $this->assertSame('Exception', $payload['detail']['label']);
        $this->assertSame('App\\Error\\DemoException', $payload['detail']['fields']['Class']);
        $this->assertArrayHasKey('Message', $payload['detail']['blocks']);
    }

    /**
     * @return void
     */
    public function testShowCapsBatchSections(): void
    {
        $batchId = '55555555-5555-5555-5555-555555555555';
        $uuid = $this->createRequest(['uri' => '/users'], $batchId);
        for ($i = 0; $i < 21; $i++) {
            $this->createQuery(['sql' => 'select ' . $i, 'hash' => 'h' . $i], $batchId);
        }

        for ($i = 0; $i < 11; $i++) {
            $this->createLog(['message' => 'log ' . $i], $batchId);
        }

        $payload = (new EntryDetail($this->repository))->show($uuid);

        $this->assertCount(20, $payload['batchGroups']['queries']['rows']);
        $this->assertSame(1, $payload['batchGroups']['queries']['more']);
        $this->assertSame(21, $payload['batchGroups']['queries']['stats']['total']);
        $this->assertSame('1', $payload['batchGroups']['queries']['rows'][0][0]);
        $this->assertCount(10, $payload['batchGroups']['logs']['rows']);
        $this->assertSame(1, $payload['batchGroups']['logs']['more']);
        $this->assertSame(11, $payload['batchGroups']['logs']['total']);
    }

    /**
     * @return void
     */
    public function testShowFullSkipsTruncation(): void
    {
        $batchId = '66666666-6666-6666-6666-666666666666';
        $longSql = 'select * from users where ' . str_repeat('active = 1 and ', 10) . 'id = 2';
        $uuid = $this->createRequest(['uri' => '/users'], $batchId);
        $this->createQuery(['sql' => $longSql], $batchId);

        $truncated = (new EntryDetail($this->repository))->show($uuid);
        $this->assertStringNotContainsString('id = 2', $truncated['batchGroups']['queries']['rows'][0][3]);

        $full = (new EntryDetail($this->repository))->show($uuid, null, true);
        $this->assertStringContainsString('id = 2', $full['batchGroups']['queries']['rows'][0][3]);
    }

    /**
     * @return void
     */
    public function testShowExceptionTraceAsListing(): void
    {
        $trace = [];
        for ($i = 0; $i < 17; $i++) {
            $trace[] = ['file' => '/app/Job.php', 'line' => 20 + $i];
        }

        $uuid = $this->createException(['trace' => $trace]);

        $payload = (new EntryDetail($this->repository))->show($uuid);
        $list = $payload['detail']['list'];

        $this->assertSame('Stack Trace', $list['label']);
        $this->assertCount(15, $list['items']);
        $this->assertSame('/app/Job.php:20', $list['items'][0]);
        $this->assertSame(2, $list['more']);
        $this->assertSame('frames', $list['moreLabel']);
        $this->assertArrayNotHasKey('Stack Trace', $payload['detail']['blocks']);
    }

    /**
     * @return void
     */
    public function testShowGroupsDuplicatesByHash(): void
    {
        $batchId = '77777777-7777-7777-7777-777777777777';
        $uuid = $this->createRequest(['uri' => '/users'], $batchId);
        $this->createQuery(['sql' => 'select 1', 'hash' => 'same'], $batchId);
        $this->createQuery(['sql' => 'select 2', 'hash' => 'same'], $batchId);
        $this->createQuery(['sql' => 'select 1', 'hash' => 'other'], $batchId);

        $payload = (new EntryDetail($this->repository))->show($uuid);
        $queries = $payload['batchGroups']['queries'];

        $this->assertSame(1, $queries['stats']['duplicateGroups']);
        $this->assertSame('DUP', $queries['rows'][0][5]);
        $this->assertSame('DUP', $queries['rows'][1][5]);
        $this->assertSame('', $queries['rows'][2][5]);
    }
}
