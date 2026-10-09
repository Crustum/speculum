<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Crustum\Speculum\Command\ShowCommand;
use Crustum\Speculum\Entry\Presentation\PresentationRegistry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Test\TestCase\Entry\CreatesSpeculumEntriesTrait;
use Crustum\Speculum\Test\TestCase\TestCaseBase;

/**
 * ShowCommand tests.
 */
class ShowCommandTest extends TestCaseBase
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
     * Execute the show command and return outputs.
     *
     * @param list<string> $args Positional arguments.
     * @param array<string, mixed> $options Console options.
     * @return array{code: int|null, out: string, err: string}
     */
    protected function executeShow(array $args, array $options = []): array
    {
        $out = new StubConsoleOutput();
        $err = new StubConsoleOutput();
        $io = new ConsoleIo($out, $err);
        $command = new ShowCommand();
        $code = $command->execute(new Arguments($args, $options, ['id']), $io);

        return [
            'code' => $code,
            'out' => implode("\n", $out->messages()),
            'err' => implode("\n", $err->messages()),
        ];
    }

    /**
     * @return void
     */
    public function testShowRendersEntryWithBatch(): void
    {
        $batchId = '55555555-5555-5555-5555-555555555555';
        $uuid = $this->createRequest(['uri' => '/users'], $batchId);
        $this->createQuery(['sql' => 'select * from users'], $batchId);

        $result = $this->executeShow([$uuid]);

        $this->assertSame(ShowCommand::CODE_SUCCESS, $result['code']);
        $this->assertStringContainsString('Request: GET /users', $result['out']);
        $this->assertStringContainsString('Related Entries', $result['out']);
        $this->assertStringContainsString('select * from users', $result['out']);
    }

    /**
     * @return void
     */
    public function testShowJsonOutputsEntryAndBatch(): void
    {
        $batchId = '66666666-6666-6666-6666-666666666666';
        $uuid = $this->createRequest(['uri' => '/users'], $batchId);
        $this->createQuery(['sql' => 'select 1'], $batchId);

        $result = $this->executeShow([$uuid], ['json' => true]);

        $this->assertSame(ShowCommand::CODE_SUCCESS, $result['code']);
        /** @var array{entry: array<string, mixed>, batch: list<array<string, mixed>>} $decoded */
        $decoded = json_decode($result['out'], true);
        $this->assertSame($uuid, $decoded['entry']['id']);
        $this->assertCount(1, $decoded['batch']);
        $this->assertSame('query', $decoded['batch'][0]['type']);
    }

    /**
     * @return void
     */
    public function testShowMissingEntryErrors(): void
    {
        $result = $this->executeShow(['00000000-0000-0000-0000-000000000000']);

        $this->assertSame(ShowCommand::CODE_ERROR, $result['code']);
        $this->assertStringContainsString('Entry not found', $result['err']);
    }

    /**
     * @return void
     */
    public function testShowLatestShortcut(): void
    {
        $this->createRequest(['uri' => '/old']);
        $this->createRequest(['uri' => '/new']);

        $result = $this->executeShow(['latest:request']);

        $this->assertSame(ShowCommand::CODE_SUCCESS, $result['code']);
        $this->assertStringContainsString('/new', $result['out']);
    }

    /**
     * @return void
     */
    public function testShowAcceptsShortUuidPrefix(): void
    {
        $uuid = $this->createRequest(
            ['uri' => '/users'],
            null,
            'abc12345-1111-4111-8111-111111111111',
        );

        $result = $this->executeShow([substr($uuid, 0, 8)]);

        $this->assertSame(ShowCommand::CODE_SUCCESS, $result['code']);
        $this->assertStringContainsString('Request: GET /users', $result['out']);
    }

    /**
     * @return void
     */
    public function testShowEntryWithoutBatch(): void
    {
        $uuid = $this->createRequest(['uri' => '/solo']);

        $result = $this->executeShow([$uuid]);

        $this->assertSame(ShowCommand::CODE_SUCCESS, $result['code']);
        $this->assertStringContainsString('Request: GET /solo', $result['out']);
        $this->assertStringContainsString('Time', $result['out']);
        $this->assertStringContainsString('Hostname', $result['out']);
        $this->assertStringNotContainsString('Related Entries', $result['out']);
    }

    /**
     * @return void
     */
    public function testShowNoEntriesOfType(): void
    {
        $batchId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $uuid = $this->createRequest(['uri' => '/users'], $batchId);
        $this->createQuery(['sql' => 'select 1'], $batchId);

        $result = $this->executeShow([$uuid], ['type' => 'log']);

        $this->assertSame(ShowCommand::CODE_SUCCESS, $result['code']);
        $this->assertStringContainsString('No batch entries of type log.', $result['out']);
    }

    /**
     * @return void
     */
    public function testShowInvalidBatchType(): void
    {
        $uuid = $this->createRequest(['uri' => '/users']);

        $result = $this->executeShow([$uuid], ['type' => 'bogus']);

        $this->assertSame(ShowCommand::CODE_ERROR, $result['code']);
        $this->assertStringContainsString('Invalid entry type: bogus', $result['err']);
        $this->assertStringContainsString('Valid types:', $result['err']);
    }

    /**
     * @return void
     */
    public function testShowLatestEmpty(): void
    {
        $result = $this->executeShow(['latest']);

        $this->assertSame(ShowCommand::CODE_ERROR, $result['code']);
        $this->assertStringContainsString('No entries found.', $result['err']);
    }

    /**
     * @return void
     */
    public function testShowLatestTypeEmpty(): void
    {
        $this->createRequest(['uri' => '/users']);

        $result = $this->executeShow(['latest:query']);

        $this->assertSame(ShowCommand::CODE_ERROR, $result['code']);
        $this->assertStringContainsString('No query entries found.', $result['err']);
    }

    /**
     * @return void
     */
    public function testShowInvalidLatestType(): void
    {
        $result = $this->executeShow(['latest:bogus']);

        $this->assertSame(ShowCommand::CODE_ERROR, $result['code']);
        $this->assertStringContainsString('Invalid entry type: bogus', $result['err']);
    }

    /**
     * @return void
     */
    public function testShowLatestAcrossTypes(): void
    {
        $this->createRequest(['uri' => '/users']);
        $this->createQuery(['sql' => 'select 9']);

        $latest = $this->executeShow(['latest']);

        $this->assertStringContainsString('select 9', $latest['out']);

        $latestRequest = $this->executeShow(['latest:request']);

        $this->assertStringContainsString('Request: GET /users', $latestRequest['out']);
    }

    /**
     * @return void
     */
    public function testShowRequestDetailFields(): void
    {
        $uuid = $this->createRequest([
            'uri' => '/users',
            'controller_action' => 'Admin:Users:index',
            'duration' => 145,
        ]);

        $result = $this->executeShow([$uuid]);

        $this->assertStringContainsString('Request: GET /users', $result['out']);
        $this->assertStringContainsString('Admin:Users:index', $result['out']);
        $this->assertStringContainsString('145ms', $result['out']);
        $this->assertStringContainsString('127.0.0.1', $result['out']);
    }

    /**
     * @return void
     */
    public function testShowMailDetail(): void
    {
        $uuid = $this->createMail([
            'subject' => 'Welcome aboard',
            'to' => ['jane@example.com' => 'Jane', 'john@example.com' => null],
        ]);

        $result = $this->executeShow([$uuid]);

        $this->assertSame(ShowCommand::CODE_SUCCESS, $result['code']);
        $this->assertStringContainsString('Mail: Welcome aboard', $result['out']);
        $this->assertStringContainsString('Jane <jane@example.com>', $result['out']);
        $this->assertStringContainsString('john@example.com', $result['out']);
        $this->assertStringContainsString('noreply@example.com', $result['out']);
    }

    /**
     * @return void
     */
    public function testShowEventWithListeners(): void
    {
        $uuid = $this->createEvent([
            'name' => 'App\\Event\\OrderCreated',
            'listeners' => ['App\\Listener\\SendMail@handle (queued)'],
        ]);

        $result = $this->executeShow([$uuid]);

        $this->assertSame(ShowCommand::CODE_SUCCESS, $result['code']);
        $this->assertStringContainsString('Event: App\\Event\\OrderCreated', $result['out']);
        $this->assertStringContainsString('SendMail@handle (queued)', $result['out']);
    }

    /**
     * @return void
     */
    public function testShowJobWithExceptionTrace(): void
    {
        $trace = [];
        for ($i = 0; $i < 11; $i++) {
            $trace[] = ['file' => '/app/Jobs/SyncOrders.php', 'line' => 22 + $i];
        }

        $uuid = $this->createJob([
            'name' => 'App\\Job\\SyncOrders',
            'exception' => ['message' => 'Sync failed', 'trace' => $trace],
        ]);

        $result = $this->executeShow([$uuid]);

        $this->assertSame(ShowCommand::CODE_SUCCESS, $result['code']);
        $this->assertStringContainsString('SyncOrders [processed]', $result['out']);
        $this->assertStringContainsString('Sync failed', $result['out']);
        $this->assertStringContainsString('Stack Trace', $result['out']);
        $this->assertStringContainsString('/app/Jobs/SyncOrders.php:22', $result['out']);
        $this->assertStringContainsString('... and 1 more frames', $result['out']);
    }

    /**
     * @return void
     */
    public function testShowOmitsEmptyUnits(): void
    {
        $uuid = $this->createJob(['name' => 'App\\Job\\SyncOrders']);

        $result = $this->executeShow([$uuid]);

        $this->assertSame(ShowCommand::CODE_SUCCESS, $result['code']);
        $this->assertStringNotContainsString('Tries', $result['out']);
        $this->assertStringNotContainsString('Timeout', $result['out']);
    }

    /**
     * @return void
     */
    public function testShowBatchWithRequestInOthers(): void
    {
        $batchId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $this->createRequest(['uri' => '/users'], $batchId);
        $this->createException(['class' => 'RuntimeException', 'message' => 'Boom'], $batchId);
        $uuid = $this->createQuery(['sql' => 'select 1'], $batchId);

        $result = $this->executeShow([$uuid]);

        $this->assertStringContainsString('Exceptions - 1', $result['out']);
        $this->assertStringContainsString('RuntimeException: Boom', $result['out']);
        $this->assertStringContainsString('Requests (1)', $result['out']);
        $this->assertStringContainsString('GET /users -> 200 (50ms)', $result['out']);
    }

    /**
     * @return void
     */
    public function testShowBatchOthersForDumpAndView(): void
    {
        $batchId = 'dddddddd-dddd-dddd-dddd-dddddddddddd';
        $dumpUuid = $this->storeEntry(EntryType::VarDump->value, ['dump' => 'hello world'], $batchId);
        $viewUuid = $this->storeEntry(EntryType::View->value, ['name' => 'dashboard'], $batchId);
        $uuid = $this->createQuery(['sql' => 'select 1'], $batchId);

        $result = $this->executeShow([$uuid]);

        $this->assertStringContainsString('Vardumps (1)', $result['out']);
        $this->assertStringContainsString('hello world', $result['out']);
        $this->assertStringContainsString(substr($dumpUuid, 0, 8), $result['out']);
        $this->assertStringContainsString('Views (1)', $result['out']);
        $this->assertStringContainsString('dashboard', $result['out']);
        $this->assertStringContainsString(substr($viewUuid, 0, 8), $result['out']);
    }

    /**
     * @return void
     */
    public function testShowQueriesStatsLine(): void
    {
        $batchId = 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee';
        $uuid = $this->createRequest(['uri' => '/users'], $batchId);
        $this->createQuery(['sql' => 'select 1', 'time' => 100, 'hash' => 'dup'], $batchId);
        $this->createQuery(['sql' => 'select 1', 'time' => 50, 'slow' => true, 'hash' => 'dup'], $batchId);
        $this->createQuery(['sql' => 'select 2', 'time' => 2, 'hash' => 'solo'], $batchId);

        $result = $this->executeShow([$uuid]);

        $this->assertStringContainsString(
            'Queries - 3 total, 152ms, 1 slow, 1 duplicate group',
            $result['out'],
        );
        $this->assertStringContainsString('DUP', $result['out']);
        $this->assertStringContainsString('SLOW', $result['out']);
    }

    /**
     * @return void
     */
    public function testShowCacheStatsLine(): void
    {
        $batchId = 'ffffffff-ffff-ffff-ffff-ffffffffffff';
        $uuid = $this->createRequest(['uri' => '/users'], $batchId);
        $this->createCache(['type' => 'hit', 'key' => 'user:1'], $batchId);
        $this->createCache(['type' => 'hit', 'key' => 'user:2'], $batchId);
        $this->createCache(['type' => 'hit', 'key' => 'user:3'], $batchId);
        $this->createCache(['type' => 'missed', 'key' => 'user:4'], $batchId);

        $result = $this->executeShow([$uuid]);

        $this->assertStringContainsString('Cache - 3 hits, 1 misses - 75% hit rate', $result['out']);
    }

    /**
     * @return void
     */
    public function testShowBatchChronologicalOrder(): void
    {
        $batchId = '11111111-2222-3333-4444-555555555555';
        $uuid = $this->createRequest(['uri' => '/users'], $batchId);
        $this->createQuery(['sql' => 'select first'], $batchId);
        $this->createQuery(['sql' => 'select second'], $batchId);

        $result = $this->executeShow([$uuid]);

        $first = strpos($result['out'], 'select first');
        $second = strpos($result['out'], 'select second');

        $this->assertNotFalse($first);
        $this->assertNotFalse($second);
        $this->assertLessThan($second, $first);
    }

    /**
     * @return void
     */
    public function testShowBatchTypeFilterSpacedList(): void
    {
        $batchId = '22222222-3333-4444-5555-666666666666';
        $uuid = $this->createRequest(['uri' => '/users'], $batchId);
        $this->createQuery(['sql' => 'select 1'], $batchId);
        $this->createCache(['type' => 'hit', 'key' => 'user:1'], $batchId);
        $this->createLog(['message' => 'hello'], $batchId);

        $result = $this->executeShow([$uuid], ['type' => 'query, cache']);

        $this->assertSame(ShowCommand::CODE_SUCCESS, $result['code']);
        $this->assertStringContainsString('select 1', $result['out']);
        $this->assertStringContainsString('user:1', $result['out']);
        $this->assertStringNotContainsString('hello', $result['out']);
    }

    /**
     * @return void
     */
    public function testShowFullDisablesTruncation(): void
    {
        $batchId = '33333333-4444-5555-6666-777777777777';
        $longSql = 'select * from users where ' . str_repeat('active = 1 and ', 10) . 'id = 2';
        $uuid = $this->createRequest(['uri' => '/users'], $batchId);
        $this->createQuery(['sql' => $longSql], $batchId);

        $default = $this->executeShow([$uuid]);

        $this->assertStringNotContainsString('id = 2', $default['out']);

        $full = $this->executeShow([$uuid], ['full' => true]);

        $this->assertStringContainsString('id = 2', $full['out']);
    }
}
