<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher\Queue;

use Cake\Console\Arguments;
use Cake\Event\EventInterface;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\JobStatus;
use Throwable;

/**
 * Resolves Queuesadilla items / league worker events into Speculum job fields.
 */
class QueuesadillaEntryBuilder
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
     * Whether the job class/name should be ignored when recording.
     *
     * @param string $job Job class or callable name.
     * @return bool
     */
    public function shouldIgnore(string $job): bool
    {
        return $this->entries->shouldIgnore($job);
    }

    /**
     * Build a pending Queuesadilla job entry.
     *
     * @param string $name Job class name.
     * @param string $connection Queue config name.
     * @param string $queue Queue name.
     * @param array<string, mixed> $data Job data.
     * @param string|null $jobId Queuesadilla job id.
     * @param int|null $tries Attempt count.
     * @param int|null $timeout Timeout seconds.
     * @return \Crustum\Speculum\Entry\IncomingEntry
     */
    public function makePendingEntry(
        string $name,
        string $connection,
        string $queue,
        array $data,
        ?string $jobId,
        ?int $tries = null,
        ?int $timeout = null,
    ): IncomingEntry {
        return IncomingEntry::make([
            'status' => JobStatus::Pending->value,
            'name' => $name,
            'connection' => $connection,
            'queue' => $queue,
            'tries' => $tries,
            'timeout' => $timeout,
            'data' => $data,
            'queue_job_id' => $jobId,
        ])->withFamilyHash($jobId);
    }

    /**
     * Resolve the job class / callable name from an item.
     *
     * @param array<string, mixed> $item Queuesadilla item.
     * @return string
     */
    public function resolveJobName(array $item): string
    {
        return $this->entries->resolveJobName($item);
    }

    /**
     * Resolve queuesadilla job id from an item for cross-process linking.
     *
     * @param array<string, mixed> $item Queuesadilla item.
     * @return string|null
     */
    public function resolveJobId(array $item): ?string
    {
        $id = $item['id'] ?? null;
        if (is_string($id) && $id !== '') {
            return $id;
        }

        if (is_int($id)) {
            return (string)$id;
        }

        return null;
    }

    /**
     * Resolve push payload data (afterEnqueue stores args flat).
     *
     * @param array<string, mixed> $item Queuesadilla item after enqueue.
     * @return array<string, mixed>
     */
    public function resolvePushedData(array $item): array
    {
        if (isset($item['args']) && is_array($item['args'])) {
            if ($item['args'] !== [] && array_is_list($item['args']) && is_array($item['args'][0])) {
                return $item['args'][0];
            }

            return $item['args'];
        }

        return $item;
    }

    /**
     * Resolve job data payload from a job / item.
     *
     * @param object $job Job object.
     * @param array<string, mixed> $item Queuesadilla item.
     * @return array<string, mixed>
     */
    public function resolveJobData(object $job, array $item): array
    {
        if (method_exists($job, 'data')) {
            $data = $job->data();
            if (is_array($data)) {
                return $data;
            }
        }

        if (isset($item['args'][0]) && is_array($item['args'][0])) {
            return $item['args'][0];
        }

        return $item;
    }

    /**
     * Resolve attempt count from job or item.
     *
     * @param object $job Job object.
     * @param array<string, mixed> $item Queuesadilla item.
     * @return int|null
     */
    public function resolveAttempts(object $job, array $item): ?int
    {
        if (method_exists($job, 'attempts')) {
            $attempts = $job->attempts();
            if (is_int($attempts)) {
                return $attempts;
            }
        }

        $attempts = $item['attempts'] ?? null;

        return is_int($attempts) ? $attempts : null;
    }

    /**
     * Resolve queue name from item, falling back to worker default.
     *
     * @param array<string, mixed> $item Queuesadilla item.
     * @param string $fallback Default queue name.
     * @return string
     */
    public function resolveItemQueue(array $item, string $fallback): string
    {
        $queue = $item['queue'] ?? null;
        if (is_string($queue) && $queue !== '') {
            return $queue;
        }

        return $fallback;
    }

    /**
     * Resolve queue config name from the Cake worker-created event.
     *
     * @param \Cake\Event\EventInterface<object> $event Worker created event.
     * @return string
     */
    public function resolveWorkerConnection(EventInterface $event): string
    {
        $args = $event->getData('args');
        if ($args instanceof Arguments) {
            $config = $args->getOption('config');
            if (is_string($config) && $config !== '') {
                return $config;
            }
        }

        return 'default';
    }

    /**
     * Resolve default queue name from event args or engine.
     *
     * @param \Cake\Event\EventInterface<object> $event Worker created event.
     * @return string
     */
    public function resolveWorkerQueueName(EventInterface $event): string
    {
        $args = $event->getData('args');
        if ($args instanceof Arguments) {
            $queue = $args->getOption('queue');
            if (is_string($queue) && $queue !== '') {
                return $queue;
            }
        }

        $engine = $event->getData('engine');
        if (is_object($engine) && method_exists($engine, 'config')) {
            $queue = $engine->config('queue');
            if (is_string($queue) && $queue !== '') {
                return $queue;
            }
        }

        return 'default';
    }

    /**
     * Extract the job object from a league event.
     *
     * @param object $leagueEvent League/queuesadilla event.
     * @return object|null
     */
    public function eventJob(object $leagueEvent): ?object
    {
        $job = $this->leagueData($leagueEvent)['job'] ?? null;

        return is_object($job) ? $job : null;
    }

    /**
     * Extract an exception from a league event when present.
     *
     * @param object $leagueEvent League/queuesadilla event.
     * @return \Throwable|null
     */
    public function eventException(object $leagueEvent): ?Throwable
    {
        $exception = $this->leagueData($leagueEvent)['exception'] ?? null;

        return $exception instanceof Throwable ? $exception : null;
    }

    /**
     * Extract duration in milliseconds from a league event when present.
     *
     * @param object $leagueEvent League/queuesadilla event.
     * @return int|null
     */
    public function eventDuration(object $leagueEvent): ?int
    {
        return $this->entries->normalizeDuration(
            $this->leagueData($leagueEvent)['duration'] ?? null,
        );
    }

    /**
     * Normalize league/queuesadilla event payload to an array.
     *
     * @param object $leagueEvent League/queuesadilla event.
     * @return array<string, mixed>
     */
    public function leagueData(object $leagueEvent): array
    {
        if (method_exists($leagueEvent, 'data')) {
            $data = $leagueEvent->data();
            if (is_array($data)) {
                return $data;
            }
        }

        if (isset($leagueEvent->data) && is_array($leagueEvent->data)) {
            return $leagueEvent->data;
        }

        return [];
    }

    /**
     * Resolve the queuesadilla item array from a job object.
     *
     * @param object $job Job object.
     * @return array<string, mixed>
     */
    public function jobItem(object $job): array
    {
        if (method_exists($job, 'item')) {
            $item = $job->item();
            if (is_array($item)) {
                return $item;
            }
        }

        return [];
    }

    /**
     * Find a Speculum job entry UUID previously recorded for a queuesadilla job id.
     *
     * @param string $jobId Queuesadilla job id (family_hash).
     * @return string|null
     */
    public function findEntryUuidByJobId(string $jobId): ?string
    {
        return $this->entries->findEntryUuidByJobId($jobId);
    }
}
