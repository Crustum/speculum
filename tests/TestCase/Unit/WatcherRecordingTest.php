<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Unit;

use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\CommandWatcher;
use Crustum\Speculum\Watcher\ExceptionWatcher;
use Crustum\Speculum\Watcher\RequestWatcher;
use RuntimeException;

/**
 * Watcher registration and recording smoke tests.
 */
class WatcherRecordingTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testExceptionWatcherRecordsEntry(): void
    {
        $watcher = new ExceptionWatcher(['enabled' => true]);
        $watcher->register();
        $watcher->recordException(new RuntimeException('speculum boom'));

        Speculum::store($this->repository);
        $entries = $this->repository->get(EntryType::Exception->value, new EntryQueryOptions());
        $this->assertNotEmpty($entries);
        $this->assertSame(EntryType::Exception->value, $entries[0]->type);
        $this->assertStringContainsString('speculum boom', (string)($entries[0]->content['message'] ?? ''));
    }

    /**
     * @return void
     */
    public function testCommandWatcherRecordsEntry(): void
    {
        $watcher = new CommandWatcher(['enabled' => true]);
        $watcher->register();
        $watcher->record('App\\Command\\DemoCommand', ['arg' => '1'], 0);

        Speculum::store($this->repository);
        $entries = $this->repository->get(EntryType::Command->value, new EntryQueryOptions());
        $this->assertNotEmpty($entries);
        $this->assertSame(EntryType::Command->value, $entries[0]->type);
        $this->assertSame('App\\Command\\DemoCommand', $entries[0]->content['command']);
    }

    /**
     * @return void
     */
    public function testRequestWatcherRegistersWithoutError(): void
    {
        $watcher = new RequestWatcher(['enabled' => true]);
        $watcher->register();
        $this->assertInstanceOf(RequestWatcher::class, $watcher);
    }
}
