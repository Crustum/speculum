<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry;

use Crustum\Speculum\Support\Avatar;
use Crustum\Speculum\Support\EditorLink;
use DateTimeInterface;
use JsonSerializable;

/**
 * Stored Speculum entry result for JSON API responses.
 *
 * @phpstan-type EntryResultArray array{
 *     id: mixed,
 *     sequence: mixed,
 *     batch_id: string,
 *     type: string,
 *     content: array<string, mixed>,
 *     tags: list<string>,
 *     family_hash: string|null,
 *     created: string,
 *     duration: int|null
 * }
 */
class EntryResult implements JsonSerializable
{
    /**
     * Entry UUID.
     *
     * @var mixed
     */
    public mixed $id;

    /**
     * Monotonic storage sequence.
     *
     * @var mixed
     */
    public mixed $sequence;

    /**
     * Batch UUID linking related entries.
     *
     * @var string
     */
    public string $batchId;

    /**
     * Entry type string.
     *
     * @var string
     */
    public string $type;

    /**
     * Family hash for grouping related exceptions.
     *
     * @var string|null
     */
    public ?string $familyHash;

    /**
     * Entry content payload.
     *
     * @var array<string, mixed>
     */
    public array $content = [];

    /**
     * When the entry was created in storage.
     *
     * @var \DateTimeInterface
     */
    public DateTimeInterface $createdAt;

    /**
     * Duration in milliseconds when recorded, else null.
     *
     * @var int|null
     */
    public ?int $duration = null;

    /**
     * Tags attached to the entry.
     *
     * @var list<string>
     */
    private array $tags;

    /**
     * Cached avatar URL for the authenticated user.
     *
     * @var string|null
     */
    protected ?string $avatar = null;

    /**
     * Create a stored entry result for API responses.
     *
     * @param mixed $id Entry UUID.
     * @param mixed $sequence Sequence number.
     * @param string $batchId Batch UUID.
     * @param string $type Entry type.
     * @param string|null $familyHash Family hash.
     * @param array<string, mixed> $content Entry content.
     * @param \DateTimeInterface $createdAt Created timestamp.
     * @param list<string> $tags Entry tags.
     * @param int|null $duration Duration in milliseconds.
     */
    public function __construct(
        mixed $id,
        mixed $sequence,
        string $batchId,
        string $type,
        ?string $familyHash,
        array $content,
        DateTimeInterface $createdAt,
        array $tags = [],
        ?int $duration = null,
    ) {
        $this->id = $id;
        $this->type = $type;
        $this->tags = $tags;
        $this->batchId = $batchId;
        $this->content = $content;
        $this->sequence = $sequence;
        $this->createdAt = $createdAt;
        $this->familyHash = $familyHash;
        $this->duration = $duration;
    }

    /**
     * Attach a resolved avatar URL to the entry user payload.
     *
     * @return $this
     */
    public function generateAvatar(): static
    {
        $this->avatar = Avatar::url($this->content['user'] ?? []);

        return $this;
    }

    /**
     * Serialize the entry for JSON API responses.
     *
     * @return EntryResultArray
     */
    public function jsonSerialize(): array
    {
        $content = EditorLink::enrichContent($this->content);

        $payload = [
            'id' => $this->id,
            'sequence' => $this->sequence,
            'batch_id' => $this->batchId,
            'type' => $this->type,
            'content' => $content,
            'tags' => $this->tags,
            'family_hash' => $this->familyHash,
            'created' => $this->createdAt->format(DateTimeInterface::ATOM),
            'duration' => $this->duration,
        ];

        if ($this->avatar !== null) {
            $payload['content']['user'] = array_merge(
                is_array($payload['content']['user'] ?? null) ? $payload['content']['user'] : [],
                ['avatar' => $this->avatar],
            );
        }

        return $payload;
    }
}
