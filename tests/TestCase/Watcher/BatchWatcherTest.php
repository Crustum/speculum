<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\Event\EventManager;
use Cake\Utility\Text;
use Crustum\BatchQueue\Data\BatchDefinition;
use Crustum\BatchQueue\Event\BatchFinished;
use Crustum\BatchQueue\Event\BatchStarted;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\BatchWatcher;
use stdClass;

/**
 * Batch watcher coverage for Crustum BatchQueue soft-dep.
 */
class BatchWatcherTest extends TestCaseBase
{
    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->markSoftPluginLoaded('Crustum/BatchQueue');
    }

    /**
     * @return void
     */
    public function testBatchWatcherRecordsStartedBatch(): void
    {
        $watcher = new BatchWatcher(['enabled' => true]);
        $watcher->register();

        $batchId = Text::uuid();
        $batch = new BatchDefinition(
            id: $batchId,
            type: BatchDefinition::TYPE_PARALLEL,
            jobs: [stdClass::class, stdClass::class, stdClass::class],
            options: ['name' => 'Demo Batch'],
            queueName: 'default',
            queueConfig: 'default',
            status: BatchDefinition::STATUS_RUNNING,
            completedJobs: 0,
            failedJobs: 0,
        );

        EventManager::instance()->dispatch(new BatchStarted($batch));

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(EntryType::Batch->value, $entries[0]->type);
        $this->assertSame($batchId, $entries[0]->id);
        $this->assertSame('Demo Batch', $entries[0]->content['name']);
        $this->assertSame(3, $entries[0]->content['totalJobs']);
        $this->assertSame(3, $entries[0]->content['pendingJobs']);
        $this->assertSame(0, $entries[0]->content['progress']);
        $this->assertSame('default', $entries[0]->content['queue']);
    }

    /**
     * @return void
     */
    public function testBatchWatcherUpdatesFinishedBatch(): void
    {
        $watcher = new BatchWatcher(['enabled' => true]);
        $watcher->register();

        $batchId = Text::uuid();
        $batch = new BatchDefinition(
            id: $batchId,
            type: BatchDefinition::TYPE_PARALLEL,
            jobs: [stdClass::class, stdClass::class],
            options: ['name' => 'Finish Me'],
            queueName: 'mails',
            queueConfig: 'redis',
            status: BatchDefinition::STATUS_RUNNING,
            completedJobs: 0,
            failedJobs: 0,
        );

        EventManager::instance()->dispatch(new BatchStarted($batch));
        $this->terminateSpeculum();

        $batch->status = BatchDefinition::STATUS_COMPLETED;
        $batch->completedJobs = 2;
        $batch->failedJobs = 0;
        EventManager::instance()->dispatch(new BatchFinished($batch));

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(100, $entries[0]->content['progress']);
        $this->assertSame(0, $entries[0]->content['pendingJobs']);
        $this->assertSame(BatchDefinition::STATUS_COMPLETED, $entries[0]->content['status']);
    }
}
