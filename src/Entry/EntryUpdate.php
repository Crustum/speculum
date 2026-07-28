<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry;

use Crustum\Speculum\Enum\EntryType;

/**
 * Pending updates to apply to an existing Speculum entry.
 */
class EntryUpdate
{
    /**
     * Target entry UUID.
     *
     * @var string
     */
    public string $uuid;

    /**
     * Entry type string.
     *
     * @var string
     */
    public string $type;

    /**
     * Content field changes to merge onto the stored entry.
     *
     * @var array<string, mixed>
     */
    public array $changes = [];

    /**
     * Tags to remove and add on the stored entry.
     *
     * @var array{removed: list<string>, added: list<string>}
     */
    public array $tagsChanges = ['removed' => [], 'added' => []];

    /**
     * Create an entry update for an existing Speculum entry.
     *
     * @param string $uuid Entry UUID.
     * @param \Crustum\Speculum\Enum\EntryType|string $type Entry type.
     * @param array<string, mixed> $changes Content changes.
     */
    public function __construct(string $uuid, EntryType|string $type, array $changes)
    {
        $this->uuid = $uuid;
        $this->type = $type instanceof EntryType ? $type->value : $type;
        $this->changes = $changes;
    }

    /**
     * Create a new entry update instance.
     *
     * @param mixed ...$arguments Constructor arguments.
     * @return static
     */
    public static function make(mixed ...$arguments): static
    {
        return new static(...$arguments);
    }

    /**
     * Merge content changes into this update.
     *
     * @param array<string, mixed> $changes Content changes.
     * @return $this
     */
    public function change(array $changes): static
    {
        $this->changes = array_merge($this->changes, $changes);

        return $this;
    }

    /**
     * Queue tags to add when the update is applied.
     *
     * @param list<string> $tags Tags to add.
     * @return $this
     */
    public function addTags(array $tags): static
    {
        $this->tagsChanges['added'] = array_values(array_unique(
            array_merge($this->tagsChanges['added'], $tags),
        ));

        return $this;
    }

    /**
     * Queue tags to remove when the update is applied.
     *
     * @param list<string> $tags Tags to remove.
     * @return $this
     */
    public function removeTags(array $tags): static
    {
        $this->tagsChanges['removed'] = array_values(array_unique(
            array_merge($this->tagsChanges['removed'], $tags),
        ));

        return $this;
    }
}
