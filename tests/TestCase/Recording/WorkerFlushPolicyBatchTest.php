<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Recording;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Queue\Job\Message as QueueJobMessage;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Recording\WorkerFlushPolicy;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\Queue\JobWatcher;
use Enqueue\Null\NullContext;
use Enqueue\Null\NullMessage;

/**
 * Worker flush keeps storage deferred but Speculum batch_id is per job.
 */
class WorkerFlushPolicyBatchTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testDeferredFlushKeepsSeparateBatchPerJob(): void
    {
        Speculum::stopRecording();
        WorkerFlushPolicy::reset();
        $previousInterval = Configure::read('Speculum.queue.worker_flush_interval');
        Configure::write('Speculum.queue.worker_flush_interval', 60);

        $watcher = new JobWatcher(['enabled' => true]);
        $watcher->register();

        $manager = EventManager::instance();

        try {
            $first = $this->dispatchMakeSearchableJob($manager, [1]);
            $second = $this->dispatchMakeSearchableJob($manager, [2]);

            $this->assertCount(2, Speculum::$entriesQueue);
            $this->assertNotSame($first, $second);
            $this->assertSame($first, Speculum::$entriesQueue[0]->batchId);
            $this->assertSame($second, Speculum::$entriesQueue[1]->batchId);

            Speculum::store();
            $entries = $this->loadSpeculumEntries();
            $this->assertCount(2, $entries);

            $jobBatches = [];
            foreach ($entries as $entry) {
                $this->assertSame(EntryType::Job->value, $entry->type);
                $jobBatches[] = $entry->batchId;
            }

            $this->assertNotSame($jobBatches[0], $jobBatches[1]);
        } finally {
            foreach (
                [
                    'Processor.message.seen',
                    'Processor.message.start',
                    'Processor.message.success',
                    'Processor.message.exception',
                    'Processor.message.reject',
                    'Processor.message.failure',
                    'Processor.message.invalid',
                ] as $eventName
            ) {
                $manager->off($eventName);
            }

            WorkerFlushPolicy::reset();
            Configure::write('Speculum.queue.worker_flush_interval', $previousInterval);
            Speculum::startRecording(false);
        }
    }

    /**
     * @param \Cake\Event\EventManager $manager Event manager
     * @param list<int> $ids MakeSearchable ids
     * @return string Job batch id stamped on the pending entry
     */
    private function dispatchMakeSearchableJob(EventManager $manager, array $ids): string
    {
        $body = json_encode([
            'class' => ['Crustum\\Scout\\Job\\MakeSearchable', 'execute'],
            'data' => [
                'source' => 'DocChunks',
                'ids' => $ids,
            ],
            'requeueOptions' => [
                'config' => 'default',
                'queue' => 'default',
            ],
        ], JSON_THROW_ON_ERROR);

        $queueMessage = new NullMessage($body);
        $jobMessage = new QueueJobMessage($queueMessage, new NullContext());

        $manager->dispatch(new Event('Processor.message.seen', $this, [
            'queueMessage' => $queueMessage,
        ]));

        $batchId = Speculum::$entriesQueue[array_key_last(Speculum::$entriesQueue)]->batchId;
        $this->assertNotNull($batchId);

        $manager->dispatch(new Event('Processor.message.success', $this, [
            'message' => $jobMessage,
            'duration' => 5,
        ]));

        return (string)$batchId;
    }
}
