<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use Throwable;
use function MongoDB\Driver\Monitoring\addSubscriber;

/**
 * Soft MongoDB command watcher (ext-mongodb CommandSubscriber APM).
 *
 * Registers only when SoftFeature::Mongo is available (`extension_loaded('mongodb')`).
 * Does not require CakeDC Mongo.
 */
class MongoWatcher extends Watcher
{
    /**
     * Whether the Mongo APM subscriber is already registered.
     *
     * @var bool
     */
    protected static bool $subscribed = false;

    /**
     * Reset subscriber flag (tests).
     *
     * @return void
     */
    public static function resetSubscribed(): void
    {
        static::$subscribed = false;
    }

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        if (static::$subscribed) {
            return;
        }

        if (!WatcherRegistry::isSoftAvailable(SoftFeature::Mongo)) {
            return;
        }

        try {
            addSubscriber(new MongoCommandSubscriber($this));
            static::$subscribed = true;
        } catch (Throwable) {
        }
    }

    /**
     * Record a Mongo command entry.
     *
     * @param string $command Command name.
     * @param float $time Duration in ms.
     * @param string|null $database Database name.
     * @param string|null $collection Collection name.
     * @param array<string, mixed> $payload Command document (truncated).
     * @param bool $failed Whether the command failed.
     * @return void
     */
    public function record(
        string $command,
        float $time,
        ?string $database = null,
        ?string $collection = null,
        array $payload = [],
        bool $failed = false,
    ): void {
        if (!Speculum::isRecording()) {
            return;
        }

        if ($this->shouldIgnore($command)) {
            return;
        }

        $slow = $this->isSlowDuration($time);
        $summary = $this->formatSummary($command, $database, $collection);
        $tags = $this->slowTags($time);

        if ($failed) {
            $tags[] = 'failed';
        }

        Speculum::recordEntry(EntryType::Mongo, IncomingEntry::make([
            'command' => $command,
            'database' => $database,
            'collection' => $collection,
            'payload' => $payload,
            'summary' => $summary,
            'time' => number_format($time, 2, '.', ''),
            'slow' => $slow,
            'failed' => $failed,
            'hash' => md5($summary),
        ])->tags($tags)->withFamilyHash(md5($summary)));
    }

    /**
     * Whether the command name is in the ignore list.
     *
     * @param string $command Command name.
     * @return bool
     */
    public function shouldIgnore(string $command): bool
    {
        $ignored = $this->options['ignore_commands'] ?? [];
        if (!is_array($ignored)) {
            return false;
        }

        $command = strtolower($command);
        foreach ($ignored as $item) {
            if (!is_string($item)) {
                continue;
            }

            if (strtolower($item) === $command) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a short command / database / collection summary label.
     *
     * @param string $command Command.
     * @param string|null $database Database.
     * @param string|null $collection Collection.
     * @return string
     */
    public function formatSummary(string $command, ?string $database, ?string $collection): string
    {
        $parts = [$command];
        if ($database !== null && $database !== '') {
            $parts[] = $database;
        }

        if ($collection !== null && $collection !== '') {
            $parts[] = $collection;
        }

        return implode(' ', $parts);
    }
}
