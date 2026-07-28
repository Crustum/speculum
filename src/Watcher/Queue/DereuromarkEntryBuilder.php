<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher\Queue;

use Cake\Event\EventInterface;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\JobStatus;
use Throwable;

/**
 * Resolves Dereuromark Queue.Job.* event payloads into Speculum job fields.
 */
class DereuromarkEntryBuilder
{
    /**
     * Shared Speculum job entry helpers (uuid lookup by family_hash).
     *
     * @var \Crustum\Speculum\Watcher\Queue\JobEntryBuilder
     */
    protected JobEntryBuilder $entries;

    /**
     * @param \Crustum\Speculum\Watcher\Queue\JobEntryBuilder|null $entries Shared entry helpers.
     */
    public function __construct(?JobEntryBuilder $entries = null)
    {
        $this->entries = $entries ?? new JobEntryBuilder();
    }

    /**
     * Extract the queued job entity/object from a Queue.Job.* event.
     *
     * @param \Cake\Event\EventInterface<object> $event Queue.Job.* event.
     * @return object|null
     */
    public function eventJob(EventInterface $event): ?object
    {
        $job = $event->getData('job');

        return is_object($job) ? $job : null;
    }

    /**
     * Resolve Speculum job name from a Dereuromark queued job.
     *
     * @param object $job Queued job entity-like object.
     * @return string
     */
    public function resolveJobName(object $job): string
    {
        $task = $job->job_task ?? null;
        if (is_string($task) && $task !== '') {
            return $task;
        }

        return 'unknown';
    }

    /**
     * Resolve string job id for family_hash linking.
     *
     * @param object $job Queued job entity-like object.
     * @return string|null
     */
    public function resolveJobId(object $job): ?string
    {
        $id = $job->id ?? null;
        if (is_int($id)) {
            return (string)$id;
        }

        if (is_string($id) && $id !== '') {
            return $id;
        }

        return null;
    }

    /**
     * Resolve job data payload from a queued job.
     *
     * @param object $job Queued job entity-like object.
     * @return array<string, mixed>
     */
    public function resolveJobData(object $job): array
    {
        $data = $job->data ?? null;

        return is_array($data) ? $data : [];
    }

    /**
     * Resolve attempt count from a queued job.
     *
     * @param object $job Queued job entity-like object.
     * @return int|null
     */
    public function resolveAttempts(object $job): ?int
    {
        $attempts = $job->attempts ?? null;

        return is_int($attempts) ? $attempts : null;
    }

    /**
     * Resolve queue / group name from a queued job.
     *
     * @param object $job Queued job entity-like object.
     * @return string
     */
    public function resolveQueue(object $job): string
    {
        $group = $job->job_group ?? null;
        if (is_string($group) && $group !== '') {
            return $group;
        }

        return 'default';
    }

    /**
     * Build a pending Dereuromark job entry.
     *
     * @param object $job Queued job entity-like object.
     * @return \Crustum\Speculum\Entry\IncomingEntry|null
     */
    public function makePendingEntry(object $job): ?IncomingEntry
    {
        $name = $this->resolveJobName($job);
        if ($name === 'unknown' || $this->entries->shouldIgnore($name)) {
            return null;
        }

        $jobId = $this->resolveJobId($job);
        $data = $this->resolveJobData($job);

        return IncomingEntry::make([
            'status' => JobStatus::Pending->value,
            'name' => $name,
            'connection' => 'default',
            'queue' => $this->resolveQueue($job),
            'tries' => $this->resolveAttempts($job),
            'timeout' => null,
            'data' => $data,
            'queue_job_id' => $jobId,
        ])->withFamilyHash($jobId);
    }

    /**
     * Build exception payload from a failed Queue.Job event.
     *
     * @param \Cake\Event\EventInterface<object> $event Queue.Job.failed event.
     * @return array<string, mixed>|string|null
     */
    public function exceptionPayload(EventInterface $event): array|string|null
    {
        $exception = $event->getData('exception');
        if ($exception instanceof Throwable) {
            return [
                'message' => $exception->getMessage(),
                'class' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        $failureMessage = $event->getData('failureMessage');
        if (is_string($failureMessage) && $failureMessage !== '') {
            return $failureMessage;
        }

        return null;
    }

    /**
     * Find a Speculum job entry UUID previously recorded for a Dereuromark job id.
     *
     * @param string $jobId Dereuromark queued job id (family_hash).
     * @return string|null
     */
    public function findEntryUuidByJobId(string $jobId): ?string
    {
        return $this->entries->findEntryUuidByJobId($jobId);
    }
}
