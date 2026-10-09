<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Crustum\Speculum\Command\ListCommand;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Test\TestCase\Entry\CreatesSpeculumEntriesTrait;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * ListCommand tests.
 */
class ListCommandTest extends TestCaseBase
{
    use CreatesSpeculumEntriesTrait;

    /**
     * Execute the list command and return outputs.
     *
     * @param list<string> $args Positional arguments.
     * @param array<string, mixed> $options Console options.
     * @return array{code: int|null, out: string, err: string}
     */
    protected function executeList(array $args = [], array $options = []): array
    {
        $out = new StubConsoleOutput();
        $err = new StubConsoleOutput();
        $io = new ConsoleIo($out, $err);
        $command = new ListCommand();
        $code = $command->execute(new Arguments($args, $options, ['type']), $io);

        return [
            'code' => $code,
            'out' => implode("\n", $out->messages()),
            'err' => implode("\n", $err->messages()),
        ];
    }

    /**
     * @return void
     */
    public function testListRendersTable(): void
    {
        $this->createRequest(['uri' => '/users']);

        $result = $this->executeList(['request']);

        $this->assertSame(ListCommand::CODE_SUCCESS, $result['code']);
        $this->assertStringContainsString('/users', $result['out']);
    }

    /**
     * @return void
     */
    public function testListJsonOutputsEntries(): void
    {
        $this->createRequest(['uri' => '/users']);

        $result = $this->executeList(['request'], ['json' => true]);

        $this->assertSame(ListCommand::CODE_SUCCESS, $result['code']);
        /** @var list<array<string, mixed>> $decoded */
        $decoded = json_decode($result['out'], true);
        $this->assertCount(1, $decoded);
        $this->assertSame('request', $decoded[0]['type']);
        $this->assertSame('/users', $decoded[0]['content']['uri']);
    }

    /**
     * @return void
     */
    public function testListRejectsInvalidType(): void
    {
        $result = $this->executeList(['bogus']);

        $this->assertSame(ListCommand::CODE_ERROR, $result['code']);
        $this->assertStringContainsString('Invalid entry type', $result['err']);
    }

    /**
     * @return void
     */
    public function testListRejectsBadLimit(): void
    {
        $result = $this->executeList([], ['limit' => 'zero']);

        $this->assertSame(ListCommand::CODE_ERROR, $result['code']);
        $this->assertStringContainsString('positive integer', $result['err']);
    }

    /**
     * @return void
     */
    public function testListWarnsWhenEmpty(): void
    {
        $result = $this->executeList(['request']);

        $this->assertSame(ListCommand::CODE_SUCCESS, $result['code']);
        $this->assertStringContainsString('No entries found.', $result['err']);
    }

    /**
     * @return void
     */
    public function testListEmptyJsonOutputsEmptyArray(): void
    {
        $result = $this->executeList([], ['json' => true]);

        $this->assertSame(ListCommand::CODE_SUCCESS, $result['code']);
        $this->assertSame([], json_decode($result['out'], true));
    }

    /**
     * @return void
     */
    public function testListFooterSingularAndCursor(): void
    {
        $this->createRequest(['uri' => '/only']);

        $single = $this->executeList(['request']);

        $this->assertStringContainsString('Showing 1 entry - No more entries', $single['out']);

        $this->createRequest(['uri' => '/second']);

        $paged = $this->executeList(['request'], ['limit' => '1']);

        $this->assertStringContainsString('Showing 1 entry - Use --before=', $paged['out']);

        preg_match('/--before=(\S+)/', $paged['out'], $matches);
        $this->assertNotEmpty($matches[1]);

        $next = $this->executeList(['request'], ['limit' => '1', 'before' => $matches[1]]);

        $this->assertStringContainsString('/only', $next['out']);
        $this->assertStringNotContainsString('/second', $next['out']);
    }

    /**
     * @param string $limit
     * @return void
     */
    #[DataProvider('invalidLimitProvider')]
    public function testListRejectsInvalidLimits(string $limit): void
    {
        $result = $this->executeList([], ['limit' => $limit]);

        $this->assertSame(ListCommand::CODE_ERROR, $result['code']);
        $this->assertStringContainsString('positive integer', $result['err']);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function invalidLimitProvider(): array
    {
        return [
            'word' => ['zero'],
            'alpha' => ['abc'],
            'zero' => ['0'],
            'negative' => ['-1'],
        ];
    }

    /**
     * @return void
     */
    public function testListMixedTypeRows(): void
    {
        $this->createRequest(['uri' => '/test']);
        $this->createCache(['type' => 'hit', 'key' => 'user:1']);

        $result = $this->executeList();

        $this->assertSame(ListCommand::CODE_SUCCESS, $result['code']);
        $this->assertStringContainsString('/test', $result['out']);
        $this->assertStringContainsString('user:1', $result['out']);
    }

    /**
     * @return void
     */
    public function testListFiltersByBatch(): void
    {
        $batchId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $this->createRequest(['uri' => '/in-batch'], $batchId);
        $this->createRequest(['uri' => '/out-of-batch']);

        $result = $this->executeList(['request'], ['batch' => $batchId]);

        $this->assertSame(ListCommand::CODE_SUCCESS, $result['code']);
        $this->assertStringContainsString('/in-batch', $result['out']);
        $this->assertStringNotContainsString('/out-of-batch', $result['out']);
    }

    /**
     * @return void
     */
    public function testListTypesRendersInfoTable(): void
    {
        WatcherRegistry::registerDefaultEntryResources();

        $result = $this->executeList([], ['types' => true]);

        $this->assertSame(ListCommand::CODE_SUCCESS, $result['code']);
        $this->assertStringContainsString('request', $result['out']);
        $this->assertStringContainsString('ai', $result['out']);
        $this->assertStringContainsString('HTTP requests with response status and duration.', $result['out']);
        $this->assertStringContainsString('redis', $result['out']);
        $this->assertStringContainsString('Not recorded by any watcher.', $result['out']);
        $this->assertStringContainsString('entry types', $result['out']);
    }

    /**
     * @return void
     */
    public function testListFiltersByShortBatchPrefix(): void
    {
        $batchId = '8c757044-aaaa-bbbb-cccc-dddddddddddd';
        $this->createRequest(['uri' => '/in-batch'], $batchId);
        $this->createRequest(['uri' => '/also-in-batch'], $batchId);
        $this->createRequest(['uri' => '/out-of-batch']);

        $result = $this->executeList(['request'], ['batch' => '8c757044']);

        $this->assertSame(ListCommand::CODE_SUCCESS, $result['code']);
        $this->assertStringContainsString('/in-batch', $result['out']);
        $this->assertStringContainsString('/also-in-batch', $result['out']);
        $this->assertStringNotContainsString('/out-of-batch', $result['out']);
    }

    /**
     * @return void
     */
    public function testListFiltersByUnknownBatchPrefix(): void
    {
        $this->createRequest(['uri' => '/test']);

        $result = $this->executeList(['request'], ['batch' => 'ffffffff']);

        $this->assertSame(ListCommand::CODE_SUCCESS, $result['code']);
        $this->assertStringContainsString('No entries found.', $result['err']);
    }

    /**
     * @return void
     */
    public function testListTypesJsonOutputsTypeRows(): void
    {
        WatcherRegistry::registerDefaultEntryResources();

        $result = $this->executeList([], ['types' => true, 'json' => true]);

        $this->assertSame(ListCommand::CODE_SUCCESS, $result['code']);
        /** @var list<array{type: string, resource: string, description: string}> $decoded */
        $decoded = json_decode($result['out'], true);
        $byType = array_column($decoded, null, 'type');

        $this->assertArrayHasKey('request', $byType);
        $this->assertArrayHasKey('ai', $byType);
        $this->assertSame('requests', $byType['request']['resource']);
        $this->assertNotEmpty($byType['ai']['description']);
    }
}
