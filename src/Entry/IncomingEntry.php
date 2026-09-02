<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry;

use Cake\Utility\Text;
use Crustum\Speculum\Contract\EntriesRepository;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\JobStatus;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * An entry waiting to be stored by Speculum.
 */
class IncomingEntry
{
    /**
     * Entry UUID.
     *
     * @var string
     */
    public string $uuid;

    /**
     * Batch UUID linking related entries.
     *
     * @var string|null
     */
    public ?string $batchId = null;

    /**
     * Entry type string (or null before assignment).
     *
     * @var string|null
     */
    public ?string $type = null;

    /**
     * Family hash for grouping related exceptions.
     *
     * @var string|null
     */
    public ?string $familyHash = null;

    /**
     * Authenticated user payload attached to the entry.
     *
     * @var mixed
     */
    public mixed $user = null;

    /**
     * Entry content payload.
     *
     * @var array<string, mixed>
     */
    public array $content = [];

    /**
     * Tags attached to the entry.
     *
     * @var list<string>
     */
    public array $tags = [];

    /**
     * When the entry was recorded.
     *
     * @var \DateTimeInterface
     */
    public DateTimeInterface $recordedAt;

    /**
     * Create an incoming entry with content and optional UUID.
     *
     * @param array<string, mixed> $content Entry content.
     * @param string|null $uuid Optional UUID.
     */
    public function __construct(array $content, ?string $uuid = null)
    {
        $this->uuid = $uuid ?: Text::uuid();
        $this->recordedAt = new DateTimeImmutable();
        $this->content = array_merge($content, ['hostname' => gethostname() ?: 'unknown']);
    }

    /**
     * Create a new incoming entry instance.
     *
     * @param mixed ...$arguments Constructor arguments.
     * @return static
     */
    public static function make(mixed ...$arguments): static
    {
        return new static(...$arguments);
    }

    /**
     * Set the batch identifier.
     *
     * @param string $batchId Batch UUID.
     * @return $this
     */
    public function batchId(string $batchId): static
    {
        $this->batchId = $batchId;

        return $this;
    }

    /**
     * Set the entry type.
     *
     * @param string $type Entry type.
     * @return $this
     */
    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set the family hash used to group related entries.
     *
     * @param string|null $familyHash Family hash.
     * @return $this
     */
    public function withFamilyHash(?string $familyHash): static
    {
        $this->familyHash = $familyHash;

        return $this;
    }

    /**
     * Attach authenticated user details and Auth tags to the entry.
     *
     * @param object $user Authenticated user object.
     * @return $this
     */
    public function user(object $user): static
    {
        $this->user = $user;

        $id = null;
        if (method_exists($user, 'getIdentifier')) {
            $id = $user->getIdentifier();
        } elseif (isset($user->id)) {
            $id = $user->id;
        }

        if (is_array($id)) {
            $id = null;
        }

        $this->content = array_merge($this->content, [
            'user' => [
                'id' => $id,
                'name' => $this->resolveUserDisplayName($user),
                'email' => $user->email ?? null,
            ],
        ]);

        if ($id !== null && $id !== '') {
            $this->tags(['Auth:' . $id]);
        }

        return $this;
    }

    /**
     * Resolve a display name from identity / CakeDC Users fields.
     *
     * @param object $user Authenticated user object.
     * @return string|null
     */
    protected function resolveUserDisplayName(object $user): ?string
    {
        foreach (['name', 'username'] as $field) {
            if (isset($user->{$field}) && is_string($user->{$field}) && $user->{$field} !== '') {
                return $user->{$field};
            }
        }

        $first = isset($user->first_name) && is_string($user->first_name) ? $user->first_name : '';
        $last = isset($user->last_name) && is_string($user->last_name) ? $user->last_name : '';
        $combined = trim($first . ' ' . $last);

        return $combined !== '' ? $combined : null;
    }

    /**
     * Merge tags into the entry.
     *
     * @param list<string> $tags Tags to merge.
     * @return $this
     */
    public function tags(array $tags): static
    {
        $this->tags = array_values(array_unique(array_merge($this->tags, $tags)));

        return $this;
    }

    /**
     * Determine whether any entry tag is currently monitored.
     *
     * @param \Crustum\Speculum\Contract\EntriesRepository $repository Entries repository.
     * @return bool
     */
    public function hasMonitoredTag(EntriesRepository $repository): bool
    {
        if ($this->tags !== []) {
            return $repository->isMonitoring($this->tags);
        }

        return false;
    }

    /**
     * Determine whether this entry is a request.
     *
     * @return bool
     */
    public function isRequest(): bool
    {
        return $this->type === EntryType::Request->value;
    }

    /**
     * Determine whether this entry is a failed HTTP request.
     *
     * @return bool
     */
    public function isFailedRequest(): bool
    {
        return $this->type === EntryType::Request->value &&
            ($this->content['response_status'] ?? 200) >= 500;
    }

    /**
     * Determine whether this entry is a database query.
     *
     * @return bool
     */
    public function isQuery(): bool
    {
        return $this->type === EntryType::Query->value;
    }

    /**
     * Determine whether this entry is a slow database query.
     *
     * @return bool
     */
    public function isSlowQuery(): bool
    {
        return $this->type === EntryType::Query->value && ($this->content['slow'] ?? false);
    }

    /**
     * Determine whether this entry is a CakeDC Auth RBAC check.
     *
     * @return bool
     */
    public function isCakeDCAuth(): bool
    {
        return $this->type === EntryType::CakeDCAuth->value;
    }

    /**
     * Determine whether this entry is a slow job.
     *
     * @return bool
     */
    public function isSlowJob(): bool
    {
        return $this->type === EntryType::Job->value && ($this->content['slow'] ?? false);
    }

    /**
     * Determine whether this entry is a slow HTTP request.
     *
     * @return bool
     */
    public function isSlowRequest(): bool
    {
        return $this->type === EntryType::Request->value && ($this->content['slow'] ?? false);
    }

    /**
     * Determine whether this entry is a slow console command.
     *
     * @return bool
     */
    public function isSlowCommand(): bool
    {
        return $this->type === EntryType::Command->value && ($this->content['slow'] ?? false);
    }

    /**
     * Determine whether this entry is an application event.
     *
     * @return bool
     */
    public function isEvent(): bool
    {
        return $this->type === EntryType::Event->value;
    }

    /**
     * Determine whether this entry is a cache operation.
     *
     * @return bool
     */
    public function isCache(): bool
    {
        return $this->type === EntryType::Cache->value;
    }

    /**
     * Determine whether this entry is a failed queue job.
     *
     * @return bool
     */
    public function isFailedJob(): bool
    {
        return $this->type === EntryType::Job->value &&
            ($this->content['status'] ?? null) === JobStatus::Failed->value;
    }

    /**
     * Determine whether this entry is a reportable exception.
     *
     * @return bool
     */
    public function isReportableException(): bool
    {
        return false;
    }

    /**
     * Determine whether this entry is an exception.
     *
     * @return bool
     */
    public function isException(): bool
    {
        return $this->type === EntryType::Exception->value;
    }

    /**
     * Determine whether this entry is a VarDump.
     *
     * @return bool
     */
    public function isVarDump(): bool
    {
        return $this->type === EntryType::VarDump->value;
    }

    /**
     * Determine whether this entry is a log message.
     *
     * @return bool
     */
    public function isLog(): bool
    {
        return $this->type === EntryType::Log->value;
    }

    /**
     * Determine whether this entry is a scheduled task.
     *
     * @return bool
     */
    public function isScheduledTask(): bool
    {
        return $this->type === EntryType::ScheduledTask->value;
    }

    /**
     * Determine whether this entry is an outbound HTTP client request.
     *
     * @return bool
     */
    public function isHttpClient(): bool
    {
        return $this->type === EntryType::HttpClient->value;
    }

    /**
     * Determine whether this entry is a broadcast.
     *
     * @return bool
     */
    public function isBroadcast(): bool
    {
        return $this->type === EntryType::Broadcast->value;
    }

    /**
     * Determine whether this entry is an Explorator search or index write.
     *
     * @return bool
     */
    public function isExplorator(): bool
    {
        return $this->type === EntryType::Explorator->value;
    }

    /**
     * Return the family hash for this entry.
     *
     * @return string|null
     */
    public function familyHash(): ?string
    {
        return $this->familyHash;
    }

    /**
     * Convert the entry into a storage array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'batch_id' => $this->batchId,
            'family_hash' => $this->familyHash,
            'type' => $this->type,
            'content' => $this->content,
            'duration' => self::durationFromContent($this->content),
            'created' => $this->recordedAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Resolve duration milliseconds from entry content (`duration` or query `time`).
     *
     * @param array<string, mixed> $content Entry content.
     * @return int|null
     */
    public static function durationFromContent(array $content): ?int
    {
        foreach (['duration', 'time'] as $key) {
            if (!isset($content[$key])) {
                continue;
            }

            if (!is_numeric($content[$key])) {
                continue;
            }

            return (int)round((float)$content[$key]);
        }

        return null;
    }
}
