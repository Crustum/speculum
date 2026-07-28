<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher\Queue;

use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\Queue\Job\Message as QueueJobMessage;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\JobStatus;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Recording\WorkerFlushPolicy;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Watcher\Watcher;
use Interop\Queue\Message as QueueMessage;
use Throwable;
use WeakMap;

/**
 * Records cakephp/queue jobs.
 *
 * Produce (soft crustum/cakephp-queue): `Crustum/Queue.Job.pushed` into the
 * current request/command buffer (no mid-request store).
 * Consume: `Processor.message.*`.
 */
class JobWatcher extends Watcher
{
    public const JOB_PUSHED = 'Crustum/Queue.Job.pushed';

    /**
     * Payload / entry helpers for cakephp/queue jobs.
     *
     * @var \Crustum\Speculum\Watcher\Queue\JobEntryBuilder
     */
    protected JobEntryBuilder $entries;

    /**
     * In-flight cakephp/queue messages keyed for duration fallback.
     *
     * @var \WeakMap<object, float>
     */
    protected WeakMap $startedAt;

    /**
     * Create the watcher and initialize helpers.
     *
     * @param array<string, mixed> $options Watcher options.
     * @param \Crustum\Speculum\Watcher\Queue\JobEntryBuilder|null $entries Entry builder.
     */
    public function __construct(array $options = [], ?JobEntryBuilder $entries = null)
    {
        parent::__construct($options);
        $this->entries = $entries ?? new JobEntryBuilder();
        $this->startedAt = new WeakMap();
    }

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        if (WatcherRegistry::isSoftAvailable(SoftFeature::CrustumQueue)) {
            EventManager::instance()->on(self::JOB_PUSHED, function (EventInterface $event): void {
                $this->recordPushedFromEvent($event);
            });
        }

        if (!WatcherRegistry::isSoftAvailable(SoftFeature::CakeQueue)) {
            return;
        }

        EventManager::instance()->on('Processor.message.seen', function (EventInterface $event): void {
            WorkerFlushPolicy::begin();
            $this->recordPendingFromEvent($event);
        });

        EventManager::instance()->on('Processor.message.start', function (EventInterface $event): void {
            $this->markStartedFromEvent($event);
        });

        EventManager::instance()->on('Processor.message.success', function (EventInterface $event): void {
            try {
                $this->recordProcessedFromEvent($event, JobStatus::Processed);
            } finally {
                WorkerFlushPolicy::end(true);
            }
        });

        EventManager::instance()->on('Processor.message.exception', function (EventInterface $event): void {
            try {
                $this->recordProcessedFromEvent($event, JobStatus::Failed);
            } finally {
                WorkerFlushPolicy::end(true);
            }
        });

        EventManager::instance()->on('Processor.message.reject', function (EventInterface $event): void {
            try {
                $this->recordProcessedFromEvent($event, JobStatus::Failed);
            } finally {
                WorkerFlushPolicy::end(true);
            }
        });

        EventManager::instance()->on('Processor.message.failure', function (EventInterface $event): void {
            try {
                $this->recordProcessedFromEvent($event, JobStatus::Failed);
            } finally {
                WorkerFlushPolicy::end(true);
            }
        });

        EventManager::instance()->on('Processor.message.invalid', function (): void {
            WorkerFlushPolicy::end(true);
        });
    }

    /**
     * Record a pending job from crustum/cakephp-queue produce (`Job.pushed`).
     *
     * Stays in the request/command Speculum buffer until that scope stores —
     * does not call {@see Speculum::store()} mid-request.
     *
     * @param \Cake\Event\EventInterface<object> $event Crustum/Queue.Job.pushed.
     * @return void
     */
    public function recordPushedFromEvent(EventInterface $event): void
    {
        if (!WatcherRegistry::isSoftAvailable(SoftFeature::CrustumQueue)) {
            return;
        }

        if (!Speculum::isRecording()) {
            return;
        }

        $payload = $event->getData('payload');
        if (!is_array($payload)) {
            return;
        }

        $body = $payload['body'] ?? null;
        $parsed = is_array($body) ? $body : $payload;
        $job = $this->entries->resolveProducedJobName($payload, $parsed);
        if ($job === 'unknown' || $this->entries->shouldIgnore($job)) {
            return;
        }

        $data = $this->entries->resolveJobData($parsed);
        $jobId = $this->entries->resolveProducedJobId($payload, $data);
        $connection = $event->getData('connection');
        if (!is_string($connection) || $connection === '') {
            $connection = 'default';
        }

        $queue = $event->getData('queue');
        if (!is_string($queue) || $queue === '') {
            $queue = $connection;
        }

        $entry = $this->entries->withJobIdFamilyHash(
            $this->entries->makeEntry([
                'status' => JobStatus::Pending->value,
                'name' => $job,
                'connection' => $connection,
                'queue' => $queue,
                'data' => $data,
                'queue_job_id' => $jobId,
            ], $data),
            $jobId,
        );

        Speculum::recordEntry(EntryType::Job, $entry);
    }

    /**
     * Record a pending job from Processor.message.seen.
     *
     * @param \Cake\Event\EventInterface<object> $event Processor.message.seen.
     * @return void
     */
    public function recordPendingFromEvent(EventInterface $event): void
    {
        $queueMessage = $event->getData('queueMessage');
        if (!$queueMessage instanceof QueueMessage) {
            $this->recordPending($event->getData());

            return;
        }

        $parsed = $this->entries->parseBody($queueMessage->getBody());
        $job = $this->entries->resolveJobName($parsed);
        if ($job === 'unknown' || $this->entries->shouldIgnore($job)) {
            return;
        }

        if (!Speculum::isRecording()) {
            return;
        }

        $data = $this->entries->resolveJobData($parsed);
        $jobId = $this->entries->resolveConsumedJobId($data);
        if ($jobId !== null) {
            $existingUuid = $this->entries->findEntryUuidByJobId($jobId);
            if ($existingUuid !== null) {
                $queueMessage->setProperty('speculum_uuid', $existingUuid);

                return;
            }
        }

        $entry = $this->entries->withJobIdFamilyHash(
            $this->entries->makeEntry([
                'status' => JobStatus::Pending->value,
                'name' => $job,
                'connection' => $this->entries->resolveConnection($parsed, $queueMessage),
                'queue' => $this->entries->resolveQueue($parsed, $queueMessage),
                'tries' => $parsed['attempts'] ?? $queueMessage->getProperty('attempts'),
                'timeout' => $parsed['timeout'] ?? null,
                'data' => $data,
                'queue_job_id' => $jobId,
            ], $data),
            $jobId,
        );

        Speculum::recordEntry(EntryType::Job, $entry);
        $queueMessage->setProperty('speculum_uuid', $entry->uuid);
    }

    /**
     * Mark execution start for duration fallback (aligned with Processor $startTime).
     *
     * @param \Cake\Event\EventInterface<object> $event Processor.message.start.
     * @return void
     */
    public function markStartedFromEvent(EventInterface $event): void
    {
        $message = $event->getData('message');
        if ($message instanceof QueueJobMessage) {
            $this->startedAt[$message->getOriginalMessage()] = microtime(true);

            return;
        }

        if ($message instanceof QueueMessage) {
            $this->startedAt[$message] = microtime(true);
        }
    }

    /**
     * Record a processed job from a Processor completion event.
     *
     * @param \Cake\Event\EventInterface<object> $event Processor completion event.
     * @param \Crustum\Speculum\Enum\JobStatus $status Job status.
     * @return void
     */
    public function recordProcessedFromEvent(EventInterface $event, JobStatus $status): void
    {
        $message = $event->getData('message');
        $duration = $this->resolveDuration($event);
        $exception = $event->getData('exception');

        if ($message instanceof QueueJobMessage) {
            $parsed = $message->getParsedBody();
            $original = $message->getOriginalMessage();
            $uuid = $original->getProperty('speculum_uuid');
            $job = $this->entries->resolveJobName($parsed);

            if ($this->entries->shouldIgnore($job)) {
                return;
            }

            $exceptionPayload = null;
            if ($exception instanceof Throwable) {
                $exceptionPayload = [
                    'message' => $exception->getMessage(),
                    'class' => $exception::class,
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ];
            } elseif ($exception !== null) {
                $exceptionPayload = (string)$exception;
            }

            if ($uuid) {
                Speculum::recordUpdate($this->makeDurationUpdate(
                    (string)$uuid,
                    EntryType::Job,
                    [
                        'status' => $status->value,
                        'exception' => $exceptionPayload,
                    ],
                    $duration,
                ));

                return;
            }

            $data = $this->entries->resolveJobData($parsed);
            $jobId = $this->entries->resolveConsumedJobId($data);
            if ($jobId !== null) {
                $linkedUuid = $this->entries->findEntryUuidByJobId($jobId);
                if ($linkedUuid !== null) {
                    Speculum::recordUpdate($this->makeDurationUpdate(
                        $linkedUuid,
                        EntryType::Job,
                        [
                            'status' => $status->value,
                            'exception' => $exceptionPayload,
                        ],
                        $duration,
                    ));

                    return;
                }
            }

            $this->recordProcessed([
                'class' => $job,
                'connection' => $this->entries->resolveConnection($parsed, $original),
                'queue' => $this->entries->resolveQueue($parsed, $original),
                'data' => $data,
                'duration' => $duration,
                'exception' => $exceptionPayload,
            ], $status);

            return;
        }

        $payload = $event->getData();
        if (is_array($payload) && $duration !== null) {
            $payload['duration'] = $duration;
        }

        $this->recordProcessed($payload, $status);
    }

    /**
     * Resolve duration in milliseconds from the Processor event or a start-message timer.
     *
     * Prefers `duration` from cakephp/queue Processor completion events.
     *
     * @param \Cake\Event\EventInterface<object> $event Processor completion event.
     * @return int|null
     */
    protected function resolveDuration(EventInterface $event): ?int
    {
        $duration = $event->getData('duration');
        if (is_numeric($duration)) {
            return (int)$duration;
        }

        $message = $event->getData('message');
        $queueMessage = null;
        if ($message instanceof QueueJobMessage) {
            $queueMessage = $message->getOriginalMessage();
        } elseif ($message instanceof QueueMessage) {
            $queueMessage = $message;
        }

        if ($queueMessage instanceof QueueMessage && isset($this->startedAt[$queueMessage])) {
            $started = $this->startedAt[$queueMessage];
            unset($this->startedAt[$queueMessage]);

            return (int)round((microtime(true) - $started) * 1000);
        }

        return null;
    }

    /**
     * Record a pending job entry from payload data.
     *
     * @param mixed $data Event data / payload.
     * @return void
     */
    public function recordPending(mixed $data): void
    {
        if (!Speculum::isRecording()) {
            return;
        }

        $payload = is_array($data) ? $data : [];
        $parsed = $this->entries->normalizePayload($payload);
        $job = $this->entries->resolveJobName($parsed);
        if ($job === 'unknown' || $this->entries->shouldIgnore($job)) {
            return;
        }

        $jobData = $this->entries->resolveJobData($parsed);
        Speculum::recordEntry(EntryType::Job, $this->entries->makeEntry([
            'status' => JobStatus::Pending->value,
            'name' => $job,
            'connection' => $this->entries->resolveConnection($parsed),
            'queue' => $this->entries->resolveQueue($parsed),
            'tries' => $parsed['attempts'] ?? $payload['attempts'] ?? null,
            'timeout' => $parsed['timeout'] ?? $payload['timeout'] ?? null,
            'data' => $jobData,
        ], $jobData));
    }

    /**
     * Record a processed job entry from payload data.
     *
     * @param mixed $data Event data / payload.
     * @param \Crustum\Speculum\Enum\JobStatus $status Job status.
     * @return void
     */
    public function recordProcessed(mixed $data, JobStatus $status): void
    {
        if (!Speculum::isRecording()) {
            return;
        }

        $payload = is_array($data) ? $data : [];
        $uuid = $payload['speculum_uuid'] ?? null;
        $duration = $this->entries->normalizeDuration($payload['duration'] ?? null);
        if ($uuid) {
            Speculum::recordUpdate($this->makeDurationUpdate(
                (string)$uuid,
                EntryType::Job,
                [
                    'status' => $status->value,
                    'exception' => $payload['exception'] ?? null,
                ],
                $duration,
            ));

            return;
        }

        $parsed = $this->entries->normalizePayload($payload);
        $job = $this->entries->resolveJobName($parsed);
        if ($job === 'unknown' || $this->entries->shouldIgnore($job)) {
            return;
        }

        $jobData = $this->entries->resolveJobData($parsed);
        $content = [
            'status' => $status->value,
            'name' => $job,
            'connection' => $this->entries->resolveConnection($parsed),
            'queue' => $this->entries->resolveQueue($parsed),
            'data' => $jobData,
            'exception' => $payload['exception'] ?? null,
        ];
        [$content] = $this->withMeasuredDuration($content, $duration);

        Speculum::recordEntry(EntryType::Job, $this->entries->makeEntry($content, $jobData));
    }
}
