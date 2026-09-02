<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher\Queue;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Queue\Job\Message as QueueJobMessage;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\JobStatus;
use Crustum\Speculum\Recording\WorkerFlushPolicy;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\Queue\JobWatcher;
use Enqueue\Null\NullContext;
use Enqueue\Null\NullMessage;

/**
 * Job watcher coverage for pending/processed recording and Processor.message.* lifecycle.
 */
class JobWatcherTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testJobRegistersEntryViaWatcherApi(): void
    {
        $watcher = new JobWatcher(['enabled' => true]);
        $payload = [
            'class' => 'App\\Job\\DemoJob',
            'connection' => 'default',
            'queue' => 'on-demand',
            'data' => ['payload' => 'Awesome CakePHP'],
        ];
        $watcher->recordPending($payload);
        $watcher->recordProcessed(
            array_merge($payload, ['speculum_uuid' => Speculum::$entriesQueue[0]->uuid]),
            JobStatus::Processed,
        );

        $this->assertNotEmpty(Speculum::$updatesQueue);

        $entries = $this->loadSpeculumEntries();
        $this->assertNotEmpty($entries);
        $entry = $entries[0];

        $this->assertSame(EntryType::Job->value, $entry->type);
        $this->assertSame('processed', $entry->content['status']);
        $this->assertSame('App\\Job\\DemoJob', $entry->content['name']);
        $this->assertSame('on-demand', $entry->content['queue']);
    }

    /**
     * @return void
     */
    public function testFailedJobsRegisterEntryWithoutUuid(): void
    {
        $watcher = new JobWatcher(['enabled' => true]);
        $watcher->recordProcessed([
            'class' => 'App\\Job\\FailingJob',
            'connection' => 'default',
            'queue' => 'default',
            'exception' => 'boom',
        ], JobStatus::Failed);

        $entries = $this->loadSpeculumEntries();
        $this->assertSame(EntryType::Job->value, $entries[0]->type);
        $this->assertSame('failed', $entries[0]->content['status']);
        $this->assertSame('boom', $entries[0]->content['exception']);
        $this->assertSame('App\\Job\\FailingJob', $entries[0]->content['name']);
    }

    /**
     * @return void
     */
    public function testCakeQueueMessageClassArrayResolvesJobName(): void
    {
        $body = json_encode([
            'class' => ['App\\Job\\Test2Job', 'execute'],
            'data' => [
                'id' => 3,
                'batch' => 12,
            ],
            'requeueOptions' => [
                'config' => 'default',
                'queue' => 'default',
            ],
        ], JSON_THROW_ON_ERROR);

        $queueMessage = new NullMessage($body);
        $context = new NullContext();
        $jobMessage = new QueueJobMessage($queueMessage, $context);

        $watcher = new JobWatcher(['enabled' => true]);

        $seen = new Event('Processor.message.seen', $this, [
            'queueMessage' => $queueMessage,
        ]);
        $watcher->recordPendingFromEvent($seen);
        $this->assertNotEmpty($queueMessage->getProperty('speculum_uuid'));

        $success = new Event('Processor.message.success', $this, [
            'message' => $jobMessage,
            'duration' => 689,
        ]);
        $watcher->recordProcessedFromEvent($success, JobStatus::Processed);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(EntryType::Job->value, $entries[0]->type);
        $this->assertSame('App\\Job\\Test2Job', $entries[0]->content['name']);
        $this->assertSame('processed', $entries[0]->content['status']);
        $this->assertSame(689, $entries[0]->content['duration']);
        $this->assertSame(['id' => 3, 'batch' => 12], $entries[0]->content['data']);
    }

    /**
     * @return void
     */
    public function testProcessedJobTagsSlowWhenDurationMeetsThreshold(): void
    {
        $watcher = new JobWatcher([
            'enabled' => true,
            'slow' => 500,
        ]);
        $watcher->recordProcessed([
            'class' => 'App\\Job\\SlowJob',
            'connection' => 'default',
            'queue' => 'default',
            'duration' => 750,
        ], JobStatus::Processed);

        $this->assertNotEmpty(Speculum::$entriesQueue);
        $this->assertTrue(Speculum::$entriesQueue[0]->content['slow']);
        $this->assertContains('slow', Speculum::$entriesQueue[0]->tags);

        $entries = $this->loadSpeculumEntries();
        $this->assertTrue($entries[0]->content['slow']);
        $this->assertSame(750, $entries[0]->content['duration']);
    }

    /**
     * @return void
     */
    public function testProcessedUpdateTagsSlowFromEventDuration(): void
    {
        $body = json_encode([
            'class' => ['App\\Job\\TimedJob', 'execute'],
            'data' => ['id' => 1],
            'requeueOptions' => [
                'config' => 'default',
                'queue' => 'default',
            ],
        ], JSON_THROW_ON_ERROR);

        $queueMessage = new NullMessage($body);
        $jobMessage = new QueueJobMessage($queueMessage, new NullContext());
        $watcher = new JobWatcher([
            'enabled' => true,
            'slow' => 200,
        ]);

        $watcher->recordPendingFromEvent(new Event('Processor.message.seen', $this, [
            'queueMessage' => $queueMessage,
        ]));
        $watcher->recordProcessedFromEvent(new Event('Processor.message.success', $this, [
            'message' => $jobMessage,
            'duration' => 350,
        ]), JobStatus::Processed);

        $this->assertNotEmpty(Speculum::$updatesQueue);
        $this->assertTrue(Speculum::$updatesQueue[0]->changes['slow']);
        $this->assertContains('slow', Speculum::$updatesQueue[0]->tagsChanges['added']);

        $entries = $this->loadSpeculumEntries();
        $this->assertTrue($entries[0]->content['slow']);
        $this->assertSame(350, $entries[0]->content['duration']);
    }

    /**
     * @return void
     */
    public function testProcessedJobBelowThresholdIsNotSlow(): void
    {
        $watcher = new JobWatcher([
            'enabled' => true,
            'slow' => 1000,
        ]);
        $watcher->recordProcessed([
            'class' => 'App\\Job\\FastJob',
            'connection' => 'default',
            'queue' => 'default',
            'duration' => 50,
        ], JobStatus::Processed);

        $this->assertFalse(Speculum::$entriesQueue[0]->content['slow']);
        $this->assertNotContains('slow', Speculum::$entriesQueue[0]->tags);
    }

    /**
     * @return void
     */
    public function testBatchJobsGetFamilyHashAndBatchUuidTag(): void
    {
        $batchId = '7484c3f7-a612-4143-9b38-294d36c74b93';
        $watcher = new JobWatcher(['enabled' => true]);
        $watcher->recordPending([
            'class' => 'App\\Job\\Test2Job',
            'connection' => 'batchjob',
            'queue' => 'batchjob',
            'data' => [
                'id' => 3,
                'batch_id' => $batchId,
                'tags' => [
                    'App\\Job\\Test2Job',
                    'Test2Job:Batch:' . $batchId,
                ],
            ],
        ]);

        $queued = Speculum::$entriesQueue[0];
        $this->assertSame($batchId, $queued->familyHash);
        $this->assertContains($batchId, $queued->tags);
        $this->assertContains('Test2Job:Batch:' . $batchId, $queued->tags);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(EntryType::Job->value, $entries[0]->type);
        $this->assertSame($batchId, $entries[0]->familyHash);

        $byFamily = $this->repository->get(
            EntryType::Job->value,
            (new EntryQueryOptions())->familyHash($batchId)->limit(-1),
        );
        $this->assertCount(1, $byFamily);

        $byTag = $this->repository->get(
            EntryType::Job->value,
            (new EntryQueryOptions())->tag($batchId)->limit(-1),
        );
        $this->assertCount(1, $byTag);
    }

    /**
     * Processor.message.* lifecycle with BatchQueue batch_id (BatchQueue NullMessage style).
     *
     * Full `queue worker` exec lives in BatchQueue CommandWorkerTest; Speculum asserts the
     * watcher side via Cake Queue Message + Processor events without a worker process.
     *
     * @return void
     */
    public function testProcessorLifecycleRecordsBatchLinkedJob(): void
    {
        $batchId = '2cd69e4f-068d-4a74-9cf7-b6e4b381c257';
        $body = json_encode([
            'class' => ['App\\Job\\Test2Job', 'execute'],
            'data' => [
                'id' => 1,
                'batch_id' => $batchId,
                'tags' => ['App\\Job\\Test2Job', 'Test2Job:Batch:' . $batchId],
            ],
            'requeueOptions' => [
                'config' => 'batchjob',
                'queue' => 'batchjob',
            ],
        ], JSON_THROW_ON_ERROR);

        $queueMessage = new NullMessage($body);
        $jobMessage = new QueueJobMessage($queueMessage, new NullContext());
        $watcher = new JobWatcher(['enabled' => true]);

        $watcher->recordPendingFromEvent(new Event('Processor.message.seen', $this, [
            'queueMessage' => $queueMessage,
        ]));
        $this->assertNotEmpty($queueMessage->getProperty('speculum_uuid'));

        $watcher->recordProcessedFromEvent(new Event('Processor.message.success', $this, [
            'message' => $jobMessage,
            'duration' => 42,
        ]), JobStatus::Processed);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(EntryType::Job->value, $entries[0]->type);
        $this->assertSame('App\\Job\\Test2Job', $entries[0]->content['name']);
        $this->assertSame('processed', $entries[0]->content['status']);
        $this->assertSame(42, $entries[0]->content['duration']);
        $this->assertSame($batchId, $entries[0]->familyHash);
        $this->assertSame('batchjob', $entries[0]->content['connection']);
        $this->assertSame('batchjob', $entries[0]->content['queue']);
    }

    /**
     * queue worker is ignore_commands — recording is off until Processor.message.seen.
     * JobWatcher must begin() before recording pending so Explorator MakeSearchable is captured.
     *
     * @return void
     */
    public function testRegisterLifecycleRecordsJobWhenBootRecordingIsOff(): void
    {
        Speculum::stopRecording();
        WorkerFlushPolicy::reset();
        $previousInterval = Configure::read('Speculum.queue.worker_flush_interval');
        Configure::write('Speculum.queue.worker_flush_interval', 0);

        $body = json_encode([
            'class' => ['Crustum\\Explorator\\Job\\MakeSearchable', 'execute'],
            'data' => [
                'source' => 'DocChunks',
                'ids' => [9],
            ],
            'requeueOptions' => [
                'config' => 'default',
                'queue' => 'default',
            ],
        ], JSON_THROW_ON_ERROR);

        $queueMessage = new NullMessage($body);
        $jobMessage = new QueueJobMessage($queueMessage, new NullContext());
        $watcher = new JobWatcher(['enabled' => true]);
        $watcher->register();

        $manager = EventManager::instance();

        try {
            $this->assertFalse(Speculum::isRecording());

            $manager->dispatch(new Event('Processor.message.seen', $this, [
                'queueMessage' => $queueMessage,
            ]));
            $this->assertTrue(Speculum::isRecording());
            $this->assertNotEmpty($queueMessage->getProperty('speculum_uuid'));
            $this->assertSame(
                'Crustum\\Explorator\\Job\\MakeSearchable',
                Speculum::$entriesQueue[0]->content['name'],
            );

            $manager->dispatch(new Event('Processor.message.success', $this, [
                'message' => $jobMessage,
                'duration' => 12,
            ]));

            $this->assertFalse(Speculum::isRecording());

            $entries = $this->loadSpeculumEntries();
            $this->assertCount(1, $entries);
            $this->assertSame('Crustum\\Explorator\\Job\\MakeSearchable', $entries[0]->content['name']);
            $this->assertSame('processed', $entries[0]->content['status']);
            $this->assertSame(12, $entries[0]->content['duration']);
            $this->assertSame('DocChunks', $entries[0]->content['data']['source']);
            $this->assertSame([9], $entries[0]->content['data']['ids']);
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
     * @return void
     */
    public function testDurationFallsBackToStartTimerWhenEventOmitsIt(): void
    {
        $body = json_encode([
            'class' => ['App\\Job\\TimedJob', 'execute'],
            'data' => ['id' => 1],
            'requeueOptions' => [
                'config' => 'default',
                'queue' => 'default',
            ],
        ], JSON_THROW_ON_ERROR);

        $queueMessage = new NullMessage($body);
        $jobMessage = new QueueJobMessage($queueMessage, new NullContext());
        $watcher = new JobWatcher(['enabled' => true]);

        $watcher->recordPendingFromEvent(new Event('Processor.message.seen', $this, [
            'queueMessage' => $queueMessage,
        ]));
        $watcher->markStartedFromEvent(new Event('Processor.message.start', $this, [
            'message' => $jobMessage,
        ]));
        usleep(1000);
        $watcher->recordProcessedFromEvent(new Event('Processor.message.success', $this, [
            'message' => $jobMessage,
        ]), JobStatus::Processed);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('processed', $entries[0]->content['status']);
        $this->assertIsInt($entries[0]->content['duration']);
        $this->assertGreaterThanOrEqual(0, $entries[0]->content['duration']);
    }

    /**
     * @return void
     */
    public function testCrustumQueuePushedRecordsPendingJob(): void
    {
        $this->markSoftPluginLoaded('Crustum/Queue');
        $watcher = new JobWatcher(['enabled' => true]);
        $uniqueId = '1710000000-abc123';

        $watcher->recordPushedFromEvent(new Event(JobWatcher::JOB_PUSHED, $this, [
            'connection' => 'default',
            'queue' => 'default',
            'payload' => [
                'id' => $uniqueId,
                'job' => 'App\\Job\\ExampleJob',
                'body' => [
                    'class' => ['App\\Job\\ExampleJob', 'execute'],
                    'args' => [[
                        'id' => 7,
                        '_uniqueId' => $uniqueId,
                        'tags' => ['App\\Job\\ExampleJob'],
                    ]],
                ],
                'tags' => ['App\\Job\\ExampleJob'],
            ],
            'options' => ['config' => 'default', 'queue' => 'default'],
        ]));

        $this->assertNotEmpty(Speculum::$entriesQueue);
        $entry = Speculum::$entriesQueue[0];
        $this->assertSame(EntryType::Job->value, $entry->type);
        $this->assertSame('pending', $entry->content['status']);
        $this->assertSame('App\\Job\\ExampleJob', $entry->content['name']);
        $this->assertSame($uniqueId, $entry->familyHash());
        $this->assertSame(7, $entry->content['data']['id']);
    }

    /**
     * @return void
     */
    public function testConsumeLinksCrustumPushedPendingByUniqueId(): void
    {
        $this->markSoftPluginLoaded('Crustum/Queue');
        $watcher = new JobWatcher(['enabled' => true]);
        $uniqueId = '1710000001-def456';

        $watcher->recordPushedFromEvent(new Event(JobWatcher::JOB_PUSHED, $this, [
            'connection' => 'default',
            'queue' => 'mails',
            'payload' => [
                'id' => $uniqueId,
                'job' => 'App\\Job\\MailJob',
                'body' => [
                    'class' => ['App\\Job\\MailJob', 'execute'],
                    'args' => [[
                        'to' => 'a@example.com',
                        '_uniqueId' => $uniqueId,
                    ]],
                ],
            ],
        ]));

        Speculum::store();
        $pendingUuid = $this->loadSpeculumEntries()[0]->id;

        $body = json_encode([
            'class' => ['App\\Job\\MailJob', 'execute'],
            'data' => [
                'to' => 'a@example.com',
                '_uniqueId' => $uniqueId,
            ],
            'requeueOptions' => [
                'config' => 'default',
                'queue' => 'mails',
            ],
        ], JSON_THROW_ON_ERROR);

        $queueMessage = new NullMessage($body);
        $jobMessage = new QueueJobMessage($queueMessage, new NullContext());

        $watcher->recordPendingFromEvent(new Event('Processor.message.seen', $this, [
            'queueMessage' => $queueMessage,
        ]));
        $this->assertSame($pendingUuid, $queueMessage->getProperty('speculum_uuid'));
        $this->assertCount(0, Speculum::$entriesQueue);

        $watcher->recordProcessedFromEvent(new Event('Processor.message.success', $this, [
            'message' => $jobMessage,
            'duration' => 15,
        ]), JobStatus::Processed);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('processed', $entries[0]->content['status']);
        $this->assertSame(15, $entries[0]->content['duration']);
        $this->assertSame($uniqueId, $entries[0]->familyHash);
    }
}
