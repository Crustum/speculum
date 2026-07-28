<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher\Queue;

use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\JobStatus;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Recording\WorkerFlushPolicy;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Watcher\Watcher;
use Throwable;
use WeakMap;

/**
 * Soft watcher for Josegonzalez/CakeQueuesadilla jobs.
 *
 * - Produce: Cake `Queuesadilla.job.pushed` (pending / “created”)
 * - Consume: league `Worker.job.*` after `Queuesadilla.worker.created`
 *
 * Uses {@see WorkerFlushPolicy::begin()} / {@see WorkerFlushPolicy::end()} on the
 * worker path so entries flush per job like cakephp/queue Processor.message.*.
 */
class QueuesadillaJobWatcher extends Watcher
{
    public const WORKER_CREATED = 'Queuesadilla.worker.created';

    public const JOB_PUSHED = 'Queuesadilla.job.pushed';

    /**
     * Queuesadilla payload / league event helpers.
     *
     * @var \Crustum\Speculum\Watcher\Queue\QueuesadillaEntryBuilder
     */
    protected QueuesadillaEntryBuilder $entries;

    /**
     * In-flight Queuesadilla jobs keyed by worker job object.
     *
     * @var \WeakMap<object, array{uuid: string, started: float}>
     */
    protected WeakMap $trackedJobs;

    /**
     * Create the watcher and initialize helpers.
     *
     * @param array<string, mixed> $options Watcher options.
     * @param \Crustum\Speculum\Watcher\Queue\QueuesadillaEntryBuilder|null $entries Entry builder.
     */
    public function __construct(array $options = [], ?QueuesadillaEntryBuilder $entries = null)
    {
        parent::__construct($options);
        $this->entries = $entries ?? new QueuesadillaEntryBuilder();
        $this->trackedJobs = new WeakMap();
    }

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        if (!WatcherRegistry::isSoftAvailable(SoftFeature::Queuesadilla)) {
            return;
        }

        EventManager::instance()->on(self::JOB_PUSHED, function (EventInterface $event): void {
            $this->recordPushed($event);
        });

        EventManager::instance()->on(self::WORKER_CREATED, function (EventInterface $event): void {
            $this->attachToWorker($event);
        });
    }

    /**
     * Record a pending job when a Queuesadilla push succeeds.
     *
     * @param \Cake\Event\EventInterface<object> $event Queuesadilla.job.pushed.
     * @return void
     */
    public function recordPushed(EventInterface $event): void
    {
        if (!Speculum::isRecording()) {
            return;
        }

        if (!(bool)$event->getData('success')) {
            return;
        }

        $item = $event->getData('item');
        if (!is_array($item)) {
            return;
        }

        $name = $this->entries->resolveJobName($item);
        if ($name === 'unknown' || $this->entries->shouldIgnore($name)) {
            return;
        }

        $config = $event->getData('config');
        $connection = is_string($config) && $config !== '' ? $config : 'default';
        $jobId = $this->entries->resolveJobId($item);

        Speculum::recordEntry(
            EntryType::Job,
            $this->entries->makePendingEntry(
                $name,
                $connection,
                $this->entries->resolveItemQueue($item, 'default'),
                $this->entries->resolvePushedData($item),
                $jobId,
                is_int($item['attempts'] ?? null) ? $item['attempts'] : null,
            ),
        );
    }

    /**
     * Attach league listeners to the worker from Queuesadilla.worker.created.
     *
     * @param \Cake\Event\EventInterface<object> $event Worker created event.
     * @return void
     */
    public function attachToWorker(EventInterface $event): void
    {
        $worker = $event->getData('worker');
        if (!is_object($worker) || !method_exists($worker, 'attachListener')) {
            return;
        }

        $connection = $this->entries->resolveWorkerConnection($event);
        $queue = $this->entries->resolveWorkerQueueName($event);

        $worker->attachListener('Worker.job.start', function (object $leagueEvent) use ($connection, $queue): void {
            WorkerFlushPolicy::begin();
            $this->recordStart($leagueEvent, $connection, $queue);
        });
        $worker->attachListener('Worker.job.success', function (object $leagueEvent): void {
            try {
                $this->recordFinish($leagueEvent, JobStatus::Processed);
            } finally {
                WorkerFlushPolicy::end(true);
            }
        });
        $worker->attachListener('Worker.job.failure', function (object $leagueEvent): void {
            try {
                $this->recordFinish($leagueEvent, JobStatus::Failed);
            } finally {
                WorkerFlushPolicy::end(true);
            }
        });
        $worker->attachListener('Worker.job.exception', function (object $leagueEvent): void {
            try {
                $this->recordFinish($leagueEvent, JobStatus::Failed);
            } finally {
                WorkerFlushPolicy::end(true);
            }
        });
    }

    /**
     * Record a pending job from Worker.job.start.
     *
     * @param object $leagueEvent League/queuesadilla event.
     * @param string $connection Queue config name.
     * @param string $queue Queue name.
     * @return void
     */
    public function recordStart(object $leagueEvent, string $connection, string $queue): void
    {
        if (!Speculum::isRecording()) {
            return;
        }

        $job = $this->entries->eventJob($leagueEvent);
        if ($job === null) {
            return;
        }

        $item = $this->entries->jobItem($job);
        $name = $this->entries->resolveJobName($item);
        if ($name === 'unknown' || $this->entries->shouldIgnore($name)) {
            return;
        }

        $jobId = $this->entries->resolveJobId($item);
        $existingUuid = $jobId !== null ? $this->entries->findEntryUuidByJobId($jobId) : null;
        if ($existingUuid !== null) {
            $this->trackedJobs[$job] = [
                'uuid' => $existingUuid,
                'started' => microtime(true),
            ];

            return;
        }

        $entry = $this->entries->makePendingEntry(
            $name,
            $connection,
            $this->entries->resolveItemQueue($item, $queue),
            $this->entries->resolveJobData($job, $item),
            $jobId,
            $this->entries->resolveAttempts($job, $item),
        );

        Speculum::recordEntry(EntryType::Job, $entry);
        $this->trackedJobs[$job] = [
            'uuid' => $entry->uuid,
            'started' => microtime(true),
        ];
    }

    /**
     * Update or record a finished job from Worker.job.success|failure|exception.
     *
     * @param object $leagueEvent League/queuesadilla event.
     * @param \Crustum\Speculum\Enum\JobStatus $status Job status.
     * @return void
     */
    public function recordFinish(object $leagueEvent, JobStatus $status): void
    {
        if (!Speculum::isRecording()) {
            return;
        }

        $job = $this->entries->eventJob($leagueEvent);
        if ($job === null) {
            return;
        }

        $exception = $this->entries->eventException($leagueEvent);
        $tracked = $this->trackedJobs[$job] ?? null;
        $duration = $this->entries->eventDuration($leagueEvent);
        if ($duration === null && $tracked !== null) {
            $duration = (int)round((microtime(true) - $tracked['started']) * 1000);
        }

        $exceptionPayload = null;
        if ($exception instanceof Throwable) {
            $exceptionPayload = [
                'message' => $exception->getMessage(),
                'class' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        if ($tracked !== null) {
            Speculum::recordUpdate($this->makeDurationUpdate(
                $tracked['uuid'],
                EntryType::Job,
                [
                    'status' => $status->value,
                    'exception' => $exceptionPayload,
                ],
                $duration,
            ));
            unset($this->trackedJobs[$job]);

            return;
        }

        $item = $this->entries->jobItem($job);
        $name = $this->entries->resolveJobName($item);
        if ($name === 'unknown' || $this->entries->shouldIgnore($name)) {
            return;
        }

        $content = [
            'status' => $status->value,
            'name' => $name,
            'connection' => 'default',
            'queue' => $this->entries->resolveItemQueue($item, 'default'),
            'data' => $this->entries->resolveJobData($job, $item),
            'exception' => $exceptionPayload,
        ];
        [$content, $tags] = $this->withMeasuredDuration($content, $duration);

        Speculum::recordEntry(
            EntryType::Job,
            IncomingEntry::make($content)->tags($tags),
        );
    }
}
