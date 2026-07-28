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
use WeakMap;

/**
 * Soft watcher for dereuromark/cakephp-queue jobs (`Queue.Job.*`).
 *
 * Produce: `Queue.Job.created` (pending in request/command buffer).
 * Consume: `Queue.Job.started` / `completed` / `failed` with WorkerFlushPolicy.
 */
class DereuromarkJobWatcher extends Watcher
{
    public const JOB_CREATED = 'Queue.Job.created';

    public const JOB_STARTED = 'Queue.Job.started';

    public const JOB_COMPLETED = 'Queue.Job.completed';

    public const JOB_FAILED = 'Queue.Job.failed';

    public const JOB_MAX_ATTEMPTS = 'Queue.Job.maxAttemptsExhausted';

    /**
     * Dereuromark payload helpers.
     *
     * @var \Crustum\Speculum\Watcher\Queue\DereuromarkEntryBuilder
     */
    protected DereuromarkEntryBuilder $entries;

    /**
     * In-flight jobs keyed by queued job object.
     *
     * @var \WeakMap<object, array{uuid: string, started: float}>
     */
    protected WeakMap $trackedJobs;

    /**
     * Create the watcher and initialize helpers.
     *
     * @param array<string, mixed> $options Watcher options.
     * @param \Crustum\Speculum\Watcher\Queue\DereuromarkEntryBuilder|null $entries Entry builder.
     */
    public function __construct(array $options = [], ?DereuromarkEntryBuilder $entries = null)
    {
        parent::__construct($options);
        $this->entries = $entries ?? new DereuromarkEntryBuilder();
        $this->trackedJobs = new WeakMap();
    }

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        if (!WatcherRegistry::isSoftAvailable(SoftFeature::DereuromarkQueue)) {
            return;
        }

        EventManager::instance()->on(self::JOB_CREATED, function (EventInterface $event): void {
            $this->recordCreated($event);
        });

        EventManager::instance()->on(self::JOB_STARTED, function (EventInterface $event): void {
            WorkerFlushPolicy::begin();
            $this->recordStarted($event);
        });

        EventManager::instance()->on(self::JOB_COMPLETED, function (EventInterface $event): void {
            try {
                $this->recordFinished($event, JobStatus::Processed);
            } finally {
                WorkerFlushPolicy::end(true);
            }
        });

        EventManager::instance()->on(self::JOB_FAILED, function (EventInterface $event): void {
            try {
                $this->recordFinished($event, JobStatus::Failed);
            } finally {
                WorkerFlushPolicy::end(true);
            }
        });

        EventManager::instance()->on(self::JOB_MAX_ATTEMPTS, function (EventInterface $event): void {
            $this->recordFinished($event, JobStatus::Failed);
        });
    }

    /**
     * Record a pending job when Dereuromark creates/enqueues a job.
     *
     * @param \Cake\Event\EventInterface<object> $event Queue.Job.created.
     * @return void
     */
    public function recordCreated(EventInterface $event): void
    {
        if (!Speculum::isRecording()) {
            return;
        }

        $job = $this->entries->eventJob($event);
        if ($job === null) {
            return;
        }

        $jobId = $this->entries->resolveJobId($job);
        if ($jobId !== null && $this->entries->findEntryUuidByJobId($jobId) !== null) {
            return;
        }

        $entry = $this->entries->makePendingEntry($job);
        if (!$entry instanceof IncomingEntry) {
            return;
        }

        Speculum::recordEntry(EntryType::Job, $entry);
    }

    /**
     * Record or link a pending job when worker starts it.
     *
     * @param \Cake\Event\EventInterface<object> $event Queue.Job.started.
     * @return void
     */
    public function recordStarted(EventInterface $event): void
    {
        if (!Speculum::isRecording()) {
            return;
        }

        $job = $this->entries->eventJob($event);
        if ($job === null) {
            return;
        }

        $jobId = $this->entries->resolveJobId($job);
        $existingUuid = $jobId !== null ? $this->entries->findEntryUuidByJobId($jobId) : null;
        if ($existingUuid !== null) {
            $this->trackedJobs[$job] = [
                'uuid' => $existingUuid,
                'started' => microtime(true),
            ];

            return;
        }

        $entry = $this->entries->makePendingEntry($job);
        if (!$entry instanceof IncomingEntry) {
            return;
        }

        Speculum::recordEntry(EntryType::Job, $entry);
        $this->trackedJobs[$job] = [
            'uuid' => $entry->uuid,
            'started' => microtime(true),
        ];
    }

    /**
     * Update or record a finished Dereuromark job.
     *
     * @param \Cake\Event\EventInterface<object> $event Queue.Job.completed|failed|maxAttemptsExhausted.
     * @param \Crustum\Speculum\Enum\JobStatus $status Job status.
     * @return void
     */
    public function recordFinished(EventInterface $event, JobStatus $status): void
    {
        if (!Speculum::isRecording()) {
            return;
        }

        $job = $this->entries->eventJob($event);
        if ($job === null) {
            return;
        }

        $tracked = $this->trackedJobs[$job] ?? null;
        $duration = null;
        if ($tracked !== null) {
            $duration = (int)round((microtime(true) - $tracked['started']) * 1000);
        }

        $exceptionPayload = $status === JobStatus::Failed
            ? $this->entries->exceptionPayload($event)
            : null;

        $uuid = $tracked['uuid'] ?? null;
        if ($uuid === null) {
            $jobId = $this->entries->resolveJobId($job);
            $uuid = $jobId !== null ? $this->entries->findEntryUuidByJobId($jobId) : null;
        }

        if ($uuid !== null) {
            Speculum::recordUpdate($this->makeDurationUpdate(
                $uuid,
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

        $name = $this->entries->resolveJobName($job);
        if ($name === 'unknown') {
            return;
        }

        $content = [
            'status' => $status->value,
            'name' => $name,
            'connection' => 'default',
            'queue' => $this->entries->resolveQueue($job),
            'data' => $this->entries->resolveJobData($job),
            'exception' => $exceptionPayload,
            'queue_job_id' => $this->entries->resolveJobId($job),
        ];
        [$content, $tags] = $this->withMeasuredDuration($content, $duration);

        Speculum::recordEntry(
            EntryType::Job,
            IncomingEntry::make($content)
                ->withFamilyHash($this->entries->resolveJobId($job))
                ->tags($tags),
        );
    }
}
