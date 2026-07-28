<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher\Queue;

use Cake\Queue\Job\Message as QueueJobMessage;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Queue\Job\ProcessPendingUpdatesJob;
use Crustum\Speculum\Queue\Job\ProcessPendingUpdatesQueuesadillaJob;
use Crustum\Speculum\Queue\Task\ProcessPendingUpdatesTask;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Interop\Queue\Message as QueueMessage;
use Throwable;

/**
 * Builds Speculum job entries and resolves cakephp/queue / Crustum Queue payloads.
 */
class JobEntryBuilder
{
    /**
     * Job classes ignored when recording.
     *
     * @var list<class-string|string>
     */
    protected array $ignoredJobClasses;

    /**
     * @param list<class-string|string>|null $ignoredJobClasses Job classes to ignore.
     */
    public function __construct(?array $ignoredJobClasses = null)
    {
        $this->ignoredJobClasses = $ignoredJobClasses ?? [
            ProcessPendingUpdatesJob::class,
            ProcessPendingUpdatesQueuesadillaJob::class,
            ProcessPendingUpdatesTask::class,
            'Crustum/Speculum.ProcessPendingUpdates',
        ];
    }

    /**
     * Build a job entry with family hash and tags.
     *
     * @param array<string, mixed> $content Entry content.
     * @param array<string, mixed> $data Job data payload.
     * @return \Crustum\Speculum\Entry\IncomingEntry
     */
    public function makeEntry(array $content, array $data): IncomingEntry
    {
        $batchId = $this->resolveBatchId($data);
        $familyHash = $batchId ?? $this->resolveConsumedJobId($data);
        $tags = $this->resolveTags($data, $batchId);
        if (!empty($content['slow'])) {
            $tags[] = 'slow';
            $tags = array_values(array_unique($tags));
        }

        return IncomingEntry::make($content)
            ->withFamilyHash($familyHash)
            ->tags($tags);
    }

    /**
     * Ensure family hash is set from a produce/consume job id when missing.
     *
     * @param \Crustum\Speculum\Entry\IncomingEntry $entry Entry.
     * @param string|null $jobId Job id.
     * @return \Crustum\Speculum\Entry\IncomingEntry
     */
    public function withJobIdFamilyHash(IncomingEntry $entry, ?string $jobId): IncomingEntry
    {
        if ($entry->familyHash() === null && $jobId !== null) {
            $entry->withFamilyHash($jobId);
        }

        return $entry;
    }

    /**
     * Determine whether the job class should be ignored.
     *
     * @param string $job Job class name.
     * @return bool
     */
    public function shouldIgnore(string $job): bool
    {
        return in_array($job, $this->ignoredJobClasses, true);
    }

    /**
     * Normalize a nested queue payload into a flat job body.
     *
     * @param array<string, mixed> $payload Raw or nested payload.
     * @return array<string, mixed>
     */
    public function normalizePayload(array $payload): array
    {
        if (isset($payload['message']) && is_array($payload['message'])) {
            return $payload['message'];
        }

        if (isset($payload['message']) && $payload['message'] instanceof QueueJobMessage) {
            return $payload['message']->getParsedBody();
        }

        return $payload;
    }

    /**
     * Resolve the job class name from a parsed body.
     *
     * @param array<string, mixed> $parsed Parsed job body.
     * @return string
     */
    public function resolveJobName(array $parsed): string
    {
        $class = $parsed['class'] ?? $parsed['job'] ?? $parsed['name'] ?? null;

        if (is_array($class) && $class !== []) {
            $jobClass = ltrim((string)$class[0], '\\');

            return $jobClass !== '' ? $jobClass : 'unknown';
        }

        if (is_string($class) && $class !== '') {
            return ltrim($class, '\\');
        }

        return 'unknown';
    }

    /**
     * Resolve job class from a Crustum produce event payload.
     *
     * @param array<string, mixed> $payload Event payload.
     * @param array<string, mixed> $parsed Parsed body.
     * @return string
     */
    public function resolveProducedJobName(array $payload, array $parsed): string
    {
        $job = $this->resolveJobName($parsed);
        if ($job !== 'unknown') {
            return $job;
        }

        $fromPayload = $payload['job'] ?? null;

        return is_string($fromPayload) && $fromPayload !== '' ? $fromPayload : 'unknown';
    }

    /**
     * Resolve job data from a parsed body.
     *
     * @param array<string, mixed> $parsed Parsed job body.
     * @return array<string, mixed>
     */
    public function resolveJobData(array $parsed): array
    {
        if (isset($parsed['data']) && is_array($parsed['data'])) {
            return $parsed['data'];
        }

        if (isset($parsed['args'][0]) && is_array($parsed['args'][0])) {
            return $parsed['args'][0];
        }

        return $parsed;
    }

    /**
     * Resolve the queue connection name for a job.
     *
     * @param array<string, mixed> $parsed Parsed job body.
     * @param \Interop\Queue\Message|null $queueMessage Queue message.
     * @return string
     */
    public function resolveConnection(array $parsed, ?QueueMessage $queueMessage = null): string
    {
        $fromOptions = $parsed['requeueOptions']['config'] ?? null;
        if (is_string($fromOptions) && $fromOptions !== '') {
            return $fromOptions;
        }

        $property = $queueMessage?->getProperty('config') ?? $queueMessage?->getProperty('connection');
        if (is_string($property) && $property !== '') {
            return $property;
        }

        return (string)($parsed['connection'] ?? 'default');
    }

    /**
     * Resolve the queue name for a job.
     *
     * @param array<string, mixed> $parsed Parsed job body.
     * @param \Interop\Queue\Message|null $queueMessage Queue message.
     * @return string
     */
    public function resolveQueue(array $parsed, ?QueueMessage $queueMessage = null): string
    {
        $fromOptions = $parsed['requeueOptions']['queue'] ?? null;
        if (is_string($fromOptions) && $fromOptions !== '') {
            return $fromOptions;
        }

        $property = $queueMessage?->getProperty('queue');
        if (is_string($property) && $property !== '') {
            return $property;
        }

        return (string)($parsed['queue'] ?? 'default');
    }

    /**
     * Parse a raw queue message body into an array.
     *
     * @param string $body Raw queue message body.
     * @return array<string, mixed>
     */
    public function parseBody(string $body): array
    {
        if ($body === '') {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Resolve BatchQueue batch id from job data.
     *
     * @param array<string, mixed> $data Job data.
     * @return string|null
     */
    public function resolveBatchId(array $data): ?string
    {
        $batchId = $data['batch_id'] ?? $data['batchId'] ?? null;
        if (!is_string($batchId) || $batchId === '') {
            return null;
        }

        return $batchId;
    }

    /**
     * Resolve Speculum tags for a job, including bare batch UUID for tag drill-down.
     *
     * @param array<string, mixed> $data Job data.
     * @param string|null $batchId Resolved batch id.
     * @return list<string>
     */
    public function resolveTags(array $data, ?string $batchId): array
    {
        $tags = [];
        if (isset($data['tags']) && is_array($data['tags'])) {
            foreach ($data['tags'] as $tag) {
                if (is_string($tag) && $tag !== '') {
                    $tags[] = $tag;
                }
            }
        }

        if ($batchId !== null) {
            $tags[] = $batchId;
        }

        return array_values(array_unique($tags));
    }

    /**
     * Resolve job id from crustum produce payload (`id` / `_uniqueId`).
     *
     * @param array<string, mixed> $payload Event payload.
     * @param array<string, mixed> $data Job data.
     * @return string|null
     */
    public function resolveProducedJobId(array $payload, array $data): ?string
    {
        $id = $payload['id'] ?? null;
        if (is_string($id) && $id !== '') {
            return $id;
        }

        return $this->resolveConsumedJobId($data);
    }

    /**
     * Resolve job id from consumed job data (`_uniqueId` from DispatchableTrait).
     *
     * @param array<string, mixed> $data Job data.
     * @return string|null
     */
    public function resolveConsumedJobId(array $data): ?string
    {
        $id = $data['_uniqueId'] ?? null;
        if (is_string($id) && $id !== '') {
            return $id;
        }

        return null;
    }

    /**
     * Find a Speculum job entry UUID previously recorded for a produce job id.
     *
     * @param string $jobId Produce `_uniqueId` / payload id (family_hash).
     * @return string|null
     */
    public function findEntryUuidByJobId(string $jobId): ?string
    {
        foreach (Speculum::$entriesQueue as $queued) {
            if ($queued->type === EntryType::Job->value && $queued->familyHash() === $jobId) {
                return $queued->uuid;
            }
        }

        try {
            $entries = Speculum::getRepository()->get(
                EntryType::Job->value,
                (new EntryQueryOptions())->familyHash($jobId)->limit(1),
            );
        } catch (Throwable) {
            return null;
        }

        if ($entries === []) {
            return null;
        }

        $id = $entries[0]->id;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Coerce a duration value to an integer millisecond count.
     *
     * @param mixed $duration Raw duration.
     * @return int|null
     */
    public function normalizeDuration(mixed $duration): ?int
    {
        if (!is_numeric($duration)) {
            return null;
        }

        return (int)$duration;
    }
}
