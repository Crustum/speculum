<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Crustum\Speculum\Entry\EntryUpdate;
use Crustum\Speculum\Enum\EntryType;

/**
 * Base Speculum watcher.
 */
abstract class Watcher
{
    /**
     * Watcher configuration options.
     *
     * @var array<string, mixed>
     */
    public array $options = [];

    /**
     * Create a watcher with the given options.
     *
     * @param array<string, mixed> $options Watcher options.
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * Register watcher listeners.
     *
     * @return void
     */
    abstract public function register(): void;

    /**
     * Whether a duration meets the configured `slow` threshold (milliseconds).
     *
     * @param float|int|null $duration Duration in milliseconds.
     * @return bool
     */
    protected function isSlowDuration(int|float|null $duration): bool
    {
        if ($duration === null || !isset($this->options['slow'])) {
            return false;
        }

        return (float)$duration >= (float)$this->options['slow'];
    }

    /**
     * Tags to attach when a duration is slow.
     *
     * @param float|int|null $duration Duration in milliseconds.
     * @return list<string>
     */
    protected function slowTags(int|float|null $duration): array
    {
        return $this->isSlowDuration($duration) ? ['slow'] : [];
    }

    /**
     * Merge `duration` / `slow` into content and return matching slow tags.
     *
     * @param array<string, mixed> $content Entry content.
     * @param int|null $duration Duration in milliseconds.
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    protected function withMeasuredDuration(array $content, ?int $duration): array
    {
        if ($duration === null) {
            return [$content, []];
        }

        $content['duration'] = $duration;
        $content['slow'] = $this->isSlowDuration($duration);

        return [$content, $this->slowTags($duration)];
    }

    /**
     * Build an entry update that includes duration and slow tagging when applicable.
     *
     * @param string $uuid Entry UUID.
     * @param \Crustum\Speculum\Enum\EntryType $type Entry type.
     * @param array<string, mixed> $changes Content changes.
     * @param int|null $duration Duration in milliseconds.
     * @return \Crustum\Speculum\Entry\EntryUpdate
     */
    protected function makeDurationUpdate(
        string $uuid,
        EntryType $type,
        array $changes,
        ?int $duration,
    ): EntryUpdate {
        if ($duration !== null) {
            $changes['duration'] = $duration;
            $changes['slow'] = $this->isSlowDuration($duration);
        }

        $update = EntryUpdate::make($uuid, $type, $changes);
        if (!empty($changes['slow'])) {
            $update->addTags(['slow']);
        }

        return $update;
    }
}
