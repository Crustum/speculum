<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\LogWatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LogLevel;
use RuntimeException;

/**
 * Log watcher tests.
 */
class LogWatcherTest extends TestCaseBase
{
    /**
     * @return list<list<string>>
     */
    public static function logLevelProvider(): array
    {
        return [
            [LogLevel::EMERGENCY],
            [LogLevel::ALERT],
            [LogLevel::CRITICAL],
            [LogLevel::ERROR],
            [LogLevel::WARNING],
            [LogLevel::NOTICE],
            [LogLevel::INFO],
            [LogLevel::DEBUG],
        ];
    }

    /**
     * @param string $level Log level.
     * @return void
     */
    #[DataProvider('logLevelProvider')]
    public function testLogWatcherRegistersEntryForAnyLevelWhenMinimumIsDebug(string $level): void
    {
        $watcher = new LogWatcher([
            'enabled' => true,
            'level' => 'debug',
        ]);
        $watcher->record($level, "Logging Level [{$level}].", [
            'user' => 'Claire Redfield',
            'role' => 'Zombie Hunter',
        ]);

        $entries = $this->loadSpeculumEntries();
        $this->assertNotEmpty($entries);
        $entry = $entries[0];

        $this->assertSame(EntryType::Log->value, $entry->type);
        $this->assertSame($level, $entry->content['level']);
        $this->assertSame("Logging Level [{$level}].", $entry->content['message']);
        $this->assertSame('Claire Redfield', $entry->content['context']['user']);
        $this->assertSame('Zombie Hunter', $entry->content['context']['role']);
    }

    /**
     * @param string $level Log level.
     * @return void
     */
    #[DataProvider('logLevelProvider')]
    public function testLogWatcherOnlyRegistersEntriesForErrorLevelPriority(string $level): void
    {
        $watcher = new LogWatcher([
            'enabled' => true,
            'level' => 'error',
        ]);
        $watcher->record($level, "Logging Level [{$level}].", [
            'user' => 'Claire Redfield',
            'role' => 'Zombie Hunter',
        ]);

        $entries = $this->loadSpeculumEntries();

        if (in_array($level, [LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR], true)) {
            $this->assertNotEmpty($entries);
            $entry = $entries[0];
            $this->assertSame(EntryType::Log->value, $entry->type);
            $this->assertSame($level, $entry->content['level']);
            $this->assertSame("Logging Level [{$level}].", $entry->content['message']);
        } else {
            $this->assertSame([], $entries);
        }
    }

    /**
     * @return void
     */
    public function testLogWatcherRegistersEntryWithExceptionKeyAsString(): void
    {
        $watcher = new LogWatcher([
            'enabled' => true,
            'level' => 'error',
        ]);
        $watcher->record('error', 'Some message', [
            'exception' => 'Some error message',
        ]);

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Log->value, $entry->type);
        $this->assertSame('error', $entry->content['level']);
        $this->assertSame('Some message', $entry->content['message']);
        $this->assertSame('Some error message', $entry->content['context']['exception']);
    }

    /**
     * @return void
     */
    public function testLogWatcherSkipsThrowableExceptionContext(): void
    {
        $watcher = new LogWatcher([
            'enabled' => true,
            'level' => 'error',
        ]);
        $watcher->record('error', 'Some message', [
            'exception' => new RuntimeException('boom'),
        ]);

        $this->assertSame([], $this->loadSpeculumEntries());
    }

    /**
     * @return void
     */
    public function testLogWatcherRecordsScopedContextByDefault(): void
    {
        $watcher = new LogWatcher([
            'enabled' => true,
            'level' => 'error',
            'scopes' => null,
        ]);
        $watcher->record('error', 'Payment failed', [
            'scope' => 'payment',
            'order_id' => 42,
        ]);

        $entries = $this->loadSpeculumEntries();
        $this->assertNotEmpty($entries);
        $this->assertSame('Payment failed', $entries[0]->content['message']);
        $this->assertSame('payment', $entries[0]->content['context']['scope']);
        $this->assertSame(42, $entries[0]->content['context']['order_id']);
    }

    /**
     * @return void
     */
    public function testLogWatcherRecordsBlazeCastSocketServerScopeByDefault(): void
    {
        $watcher = new LogWatcher([
            'enabled' => true,
            'level' => 'error',
        ]);
        $watcher->record('error', 'WS boom', [
            'scope' => ['socket.server', 'socket.server.speculum'],
        ]);

        $entries = $this->loadSpeculumEntries();
        $this->assertNotEmpty($entries);
        $this->assertSame(
            ['socket.server', 'socket.server.speculum'],
            $entries[0]->content['context']['scope'],
        );
    }

    /**
     * @return void
     */
    public function testLogWatcherListIncludesUnscopedWhenConfigured(): void
    {
        $watcher = new LogWatcher([
            'enabled' => true,
            'level' => 'error',
            'scopes' => ['payment'],
            'include_unscoped' => true,
        ]);
        $watcher->record('error', 'Unscoped error', ['scope' => []]);

        $entries = $this->loadSpeculumEntries();
        $this->assertNotEmpty($entries);
        $this->assertSame('Unscoped error', $entries[0]->content['message']);
    }

    /**
     * @return void
     */
    public function testLogWatcherListExcludesUnscopedWhenDisabled(): void
    {
        $watcher = new LogWatcher([
            'enabled' => true,
            'level' => 'error',
            'scopes' => ['payment'],
            'include_unscoped' => false,
        ]);
        $watcher->record('error', 'Unscoped error', ['scope' => []]);

        $this->assertSame([], $this->loadSpeculumEntries());
    }

    /**
     * @return void
     */
    public function testLogWatcherListSkipsOtherScopes(): void
    {
        $watcher = new LogWatcher([
            'enabled' => true,
            'level' => 'error',
            'scopes' => ['payment'],
            'include_unscoped' => true,
        ]);
        $watcher->record('error', 'Other scope', ['scope' => ['orders']]);

        $this->assertSame([], $this->loadSpeculumEntries());
    }

    /**
     * @return void
     */
    public function testLogWatcherEmptyScopesMeansUnscopedOnly(): void
    {
        $watcher = new LogWatcher([
            'enabled' => true,
            'level' => 'error',
            'scopes' => [],
        ]);
        $watcher->record('error', 'Scoped', ['scope' => ['payment']]);
        $this->assertSame([], $this->loadSpeculumEntries());

        $watcher->record('error', 'Unscoped', ['scope' => []]);
        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('Unscoped', $entries[0]->content['message']);
    }
}
