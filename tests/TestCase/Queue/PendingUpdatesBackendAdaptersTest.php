<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Queue;

use Crustum\Speculum\Contract\EntriesRepository;
use Crustum\Speculum\Entry\EntryUpdate;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Queue\Job\ProcessPendingUpdatesQueuesadillaJob;
use Crustum\Speculum\Queue\JobDispatcher;
use Crustum\Speculum\Queue\JobDispatcherInterface;
use Crustum\Speculum\Queue\Task\ProcessPendingUpdatesTask;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;

/**
 * Soft backend adapters for pending updates.
 */
class PendingUpdatesBackendAdaptersTest extends TestCaseBase
{
    /**
     * @return void
     */
    protected function tearDown(): void
    {
        JobDispatcher::setInstance(null);
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testQueuesadillaJobPerformsUpdates(): void
    {
        $failedUpdate = new EntryUpdate('missing-uuid', EntryType::Job->value, ['status' => 'processed']);

        /** @var \Crustum\Speculum\Contract\EntriesRepository&\PHPUnit\Framework\MockObject\MockObject $repository */
        $repository = $this->createMock(EntriesRepository::class);
        $repository->expects($this->once())->method('update')->willReturn([$failedUpdate]);
        Speculum::setRepository($repository);

        $dispatcher = new class implements JobDispatcherInterface {
            public int $pushes = 0;

            public function isAvailable(): bool
            {
                return true;
            }

            public function push(array $data, array $options = []): void
            {
                $this->pushes++;
            }
        };
        JobDispatcher::setInstance($dispatcher);

        $job = new class {
            /**
             * @param string|null $key Data key.
             * @param mixed $default Default.
             * @return mixed
             */
            public function data(?string $key = null, mixed $default = null): mixed
            {
                $payload = [
                    'pendingUpdates' => [[
                        'uuid' => 'missing-uuid',
                        'type' => EntryType::Job->value,
                        'changes' => ['status' => 'processed'],
                    ]],
                    'attempt' => 0,
                ];

                if ($key === null) {
                    return $payload;
                }

                return $payload[$key] ?? $default;
            }
        };

        (new ProcessPendingUpdatesQueuesadillaJob())->perform($job);
        $this->assertSame(1, $dispatcher->pushes);
    }

    /**
     * @return void
     */
    public function testDereuromarkTaskRunsProcessor(): void
    {
        $failedUpdate = new EntryUpdate('missing-uuid', EntryType::Job->value, ['status' => 'processed']);

        /** @var \Crustum\Speculum\Contract\EntriesRepository&\PHPUnit\Framework\MockObject\MockObject $repository */
        $repository = $this->createMock(EntriesRepository::class);
        $repository->expects($this->once())->method('update')->willReturn([$failedUpdate]);
        Speculum::setRepository($repository);

        $dispatcher = new class implements JobDispatcherInterface {
            public int $pushes = 0;

            public function isAvailable(): bool
            {
                return true;
            }

            public function push(array $data, array $options = []): void
            {
                $this->pushes++;
            }
        };
        JobDispatcher::setInstance($dispatcher);

        $task = new ProcessPendingUpdatesTask();
        $task->run([
            'pendingUpdates' => [[
                'uuid' => 'missing-uuid',
                'type' => EntryType::Job->value,
                'changes' => ['status' => 'processed'],
            ]],
            'attempt' => 0,
        ], 1);

        $this->assertSame(1, $dispatcher->pushes);
    }
}
