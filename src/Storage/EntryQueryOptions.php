<?php
declare(strict_types=1);

namespace Crustum\Speculum\Storage;

use Cake\Http\ServerRequest;

/**
 * Options used when querying Speculum entries.
 */
class EntryQueryOptions
{
    public const DEFAULT_LIMIT = 50;

    public const MAX_CLIENT_LIMIT = 100;

    public const UNLIMITED = -1;

    /**
     * Filter by batch id.
     *
     * @var string|null
     */
    public ?string $batchId = null;

    /**
     * Filter by entry tag (comma-separated values supported by callers).
     *
     * @var string|null
     */
    public ?string $tag = null;

    /**
     * Filter by exception family hash.
     *
     * @var string|null
     */
    public ?string $familyHash = null;

    /**
     * Return entries with sequence strictly less than this value.
     *
     * @var mixed
     */
    public mixed $beforeSequence = null;

    /**
     * Duration cursor paired with beforeSequence when ordering by duration.
     *
     * @var int|null
     */
    public ?int $beforeDuration = null;

    /**
     * Return entries with sequence strictly greater than this value.
     *
     * @var mixed
     */
    public mixed $afterSequence = null;

    /**
     * Restrict results to these entry UUIDs.
     *
     * @var list<string>|null
     */
    public ?array $uuids = null;

    /**
     * Minimum duration in milliseconds (inclusive); null disables the filter.
     *
     * @var int|null
     */
    public ?int $minDuration = null;

    /**
     * Sort field: `sequence` (default) or `duration`.
     *
     * @var string
     */
    public string $orderBy = 'sequence';

    /**
     * Sort direction: `asc` or `desc`.
     *
     * @var string
     */
    public string $orderDirection = 'desc';

    /**
     * Maximum number of entries to return.
     *
     * @var int
     */
    public int $limit = self::DEFAULT_LIMIT;

    /**
     * Build query options from an HTTP request.
     *
     * Client `take` is clamped to 1…{@MAX_CLIENT_LIMIT}. Negative values
     * (including `-1`) are not unlimited from the API — use `limit(UNLIMITED)` internally.
     *
     * @param \Cake\Http\ServerRequest $request Incoming request.
     * @return static
     */
    public static function fromRequest(ServerRequest $request): static
    {
        $data = $request->getData() + $request->getQueryParams();

        return (new static())
            ->batchId(isset($data['batch_id']) ? (string)$data['batch_id'] : null)
            ->uuids(
                isset($data['uuids']) && is_array($data['uuids'])
                    ? array_values(array_filter($data['uuids'], is_string(...)))
                    : null,
            )
            ->beforeSequence($data['before'] ?? null)
            ->beforeDuration(static::parseOptionalInt($data['before_duration'] ?? null))
            ->tag(isset($data['tag']) ? (string)$data['tag'] : null)
            ->familyHash(isset($data['family_hash']) ? (string)$data['family_hash'] : null)
            ->minDuration(static::parseOptionalInt($data['min_duration'] ?? null))
            ->orderBy(isset($data['order_by']) ? (string)$data['order_by'] : 'sequence')
            ->orderDirection(isset($data['order_direction']) ? (string)$data['order_direction'] : 'desc')
            ->limit(static::clampClientTake($data['take'] ?? null));
    }

    /**
     * Parse an optional non-negative integer from request input.
     *
     * @param mixed $value Raw value.
     * @return int|null
     */
    protected static function parseOptionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $parsed = (int)$value;

        return $parsed >= 0 ? $parsed : null;
    }

    /**
     * Clamp a client-supplied `take` value for HTTP list endpoints.
     *
     * @param mixed $take Raw take from request.
     * @return int
     */
    public static function clampClientTake(mixed $take): int
    {
        if ($take === null || $take === '') {
            return self::DEFAULT_LIMIT;
        }

        if (!is_numeric($take)) {
            return self::DEFAULT_LIMIT;
        }

        $value = (int)$take;
        if ($value < 1) {
            return self::DEFAULT_LIMIT;
        }

        return min($value, self::MAX_CLIENT_LIMIT);
    }

    /**
     * Create options scoped to a batch identifier.
     *
     * @param string|null $batchId Batch UUID.
     * @return static
     */
    public static function forBatchId(?string $batchId): static
    {
        return (new static())->batchId($batchId);
    }

    /**
     * Set the batch identifier filter.
     *
     * @param string|null $batchId Batch UUID.
     * @return $this
     */
    public function batchId(?string $batchId): static
    {
        $this->batchId = $batchId;

        return $this;
    }

    /**
     * Set the entry UUID filter list.
     *
     * @param list<string>|null $uuids Entry UUIDs.
     * @return $this
     */
    public function uuids(?array $uuids): static
    {
        $this->uuids = $uuids;

        return $this;
    }

    /**
     * Set the exclusive upper bound on entry sequence.
     *
     * @param mixed $id Sequence bound.
     * @return $this
     */
    public function beforeSequence(mixed $id): static
    {
        $this->beforeSequence = $id;

        return $this;
    }

    /**
     * Set the duration cursor used with beforeSequence when sorting by duration.
     *
     * @param int|null $duration Duration cursor.
     * @return $this
     */
    public function beforeDuration(?int $duration): static
    {
        $this->beforeDuration = $duration;

        return $this;
    }

    /**
     * Set the exclusive lower bound on entry sequence.
     *
     * @param mixed $id Sequence bound.
     * @return $this
     */
    public function afterSequence(mixed $id): static
    {
        $this->afterSequence = $id;

        return $this;
    }

    /**
     * Set the tag filter.
     *
     * @param string|null $tag Tag filter.
     * @return $this
     */
    public function tag(?string $tag): static
    {
        $this->tag = $tag;

        return $this;
    }

    /**
     * Set the family hash filter.
     *
     * @param string|null $familyHash Family hash filter.
     * @return $this
     */
    public function familyHash(?string $familyHash): static
    {
        $this->familyHash = $familyHash;

        return $this;
    }

    /**
     * Set the minimum duration filter (milliseconds).
     *
     * @param int|null $minDuration Minimum duration, or null to disable.
     * @return $this
     */
    public function minDuration(?int $minDuration): static
    {
        $this->minDuration = $minDuration;

        return $this;
    }

    /**
     * Set the sort field (`sequence` or `duration`).
     *
     * @param string $orderBy Sort field.
     * @return $this
     */
    public function orderBy(string $orderBy): static
    {
        $this->orderBy = $orderBy === 'duration' ? 'duration' : 'sequence';

        return $this;
    }

    /**
     * Set the sort direction (`asc` or `desc`).
     *
     * @param string $orderDirection Sort direction.
     * @return $this
     */
    public function orderDirection(string $orderDirection): static
    {
        $this->orderDirection = strtolower($orderDirection) === 'asc' ? 'asc' : 'desc';

        return $this;
    }

    /**
     * Set the maximum number of results to return.
     *
     * @param int $limit Result limit ({@see UNLIMITED} for no SQL LIMIT; internal only).
     * @return $this
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }
}
