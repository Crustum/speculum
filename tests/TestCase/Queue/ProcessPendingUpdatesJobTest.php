<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Queue;

use Cake\Queue\Job\Message as QueueJobMessage;
use Crustum\Speculum\Contract\EntriesRepository;
use Crustum\Speculum\Entry\EntryUpdate;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Queue\Job\ProcessPendingUpdatesJob;
use Crustum\Speculum\Queue\JobDispatcher;
use Crustum\Speculum\Queue\JobDispatcherInterface;
use Crustum\Speculum\Queue\PendingUpdatesProcessor;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Enqueue\Null\NullContext;
use Enqueue\Null\NullMessage;
use Interop\Queue\Processor;

/**
 * ProcessPendingUpdatesJob / PendingUpdatesProcessor tests.
 */
class ProcessPendingUpdatesJobTest extends TestCaseBase
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
    public function testPendingUpdatesAreApplied(): void
    {
        $entry = IncomingEntry::make([
            'status' => 'pending',
            'name' => 'App\\Job\\DemoJob',
        ])->type(EntryType::Job->value)->batchId('11111111-1111-1111-1111-111111111111');
        $this->repository->store([$entry]);

        $result = $this->runJob([
            'pendingUpdates' => [[
                'uuid' => $entry->uuid,
                'type' => EntryType::Job->value,
                'changes' => ['status' => 'processed'],
            ]],
            'attempt' => 0,
        ]);

        $this->assertSame(Processor::ACK, $result);
        $found = $this->repository->find($entry->uuid);
        $this->assertSame('processed', $found->content['status']);
    }

    /**
     * @return void
     */
    public function testPendingUpdatesMayStayPendingAndRequeue(): void
    {
        $failedUpdate = new EntryUpdate('missing-uuid', EntryType::Job->value, ['status' => 'processed']);

        /** @var \Crustum\Speculum\Contract\EntriesRepository&\PHPUnit\Framework\MockObject\MockObject $repository */
        $repository = $this->createMock(EntriesRepository::class);
        $repository
            ->expects($this->once())
            ->method('update')
            ->willReturn([$failedUpdate]);

        Speculum::setRepository($repository);

        $dispatcher = new class implements JobDispatcherInterface {
            /**
             * @var list<array{data: array, options: array}>
             */
            public array $pushes = [];

            public function isAvailable(): bool
            {
                return true;
            }

            public function push(array $data, array $options = []): void
            {
                $this->pushes[] = ['data' => $data, 'options' => $options];
            }
        };
        JobDispatcher::setInstance($dispatcher);

        $result = $this->runJob([
            'pendingUpdates' => [[
                'uuid' => 'missing-uuid',
                'type' => EntryType::Job->value,
                'changes' => ['status' => 'processed'],
            ]],
            'attempt' => 0,
        ]);

        $this->assertSame(Processor::ACK, $result);
        $this->assertCount(1, $dispatcher->pushes);
        $this->assertSame(1, $dispatcher->pushes[0]['data']['attempt']);
        $this->assertSame('missing-uuid', $dispatcher->pushes[0]['data']['pendingUpdates'][0]['uuid']);
    }

    /**
     * @return void
     */
    public function testPendingUpdatesStopRequeueAfterThreeAttempts(): void
    {
        /** @var \Crustum\Speculum\Contract\EntriesRepository&\PHPUnit\Framework\MockObject\MockObject $repository */
        $repository = $this->createMock(EntriesRepository::class);
        $repository
            ->expects($this->once())
            ->method('update')
            ->willReturn([
                new EntryUpdate('missing-uuid', EntryType::Job->value, ['status' => 'processed']),
            ]);

        Speculum::setRepository($repository);

        $dispatcher = new class implements JobDispatcherInterface {
            /**
             * @var list<array{data: array, options: array}>
             */
            public array $pushes = [];

            public function isAvailable(): bool
            {
                return true;
            }

            public function push(array $data, array $options = []): void
            {
                $this->pushes[] = ['data' => $data, 'options' => $options];
            }
        };
        JobDispatcher::setInstance($dispatcher);

        $result = $this->runJob([
            'pendingUpdates' => [[
                'uuid' => 'missing-uuid',
                'type' => EntryType::Job->value,
                'changes' => ['status' => 'processed'],
            ]],
            'attempt' => 2,
        ]);

        $this->assertSame(Processor::ACK, $result);
        $this->assertSame([], $dispatcher->pushes);
    }

    /**
     * @return void
     */
    public function testProcessorRequeueUsesDispatcher(): void
    {
        $failedUpdate = new EntryUpdate('missing-uuid', EntryType::Job->value, ['status' => 'processed']);

        /** @var \Crustum\Speculum\Contract\EntriesRepository&\PHPUnit\Framework\Stub\Stub $repository */
        $repository = $this->createStub(EntriesRepository::class);
        $repository->method('update')->willReturn([$failedUpdate]);
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

        (new PendingUpdatesProcessor())->process([
            [
                'uuid' => 'missing-uuid',
                'type' => EntryType::Job->value,
                'changes' => ['status' => 'processed'],
            ],
        ], 0);

        $this->assertSame(1, $dispatcher->pushes);
    }

    /**
     * @param array<string, mixed> $data Job data payload.
     * @return string|null
     */
    protected function runJob(array $data): ?string
    {
        $body = json_encode([
            'class' => [ProcessPendingUpdatesJob::class, 'execute'],
            'data' => $data,
        ], JSON_THROW_ON_ERROR);

        $queueMessage = new NullMessage($body);
        $message = new QueueJobMessage($queueMessage, new NullContext());

        return (new ProcessPendingUpdatesJob())->execute($message);
    }
}
