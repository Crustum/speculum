<?php
declare(strict_types=1);

namespace Crustum\Speculum;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\Routing\Router;
use Cake\Utility\Text;
use Closure;
use Crustum\Speculum\Contract\EntriesRepository;
use Crustum\Speculum\Contract\TerminableRepository;
use Crustum\Speculum\Entry\EntryUpdate;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Entry\IncomingVarDumpEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Listener\StorageListener;
use Crustum\Speculum\Queue\JobDispatcher;
use Crustum\Speculum\Queue\PendingUpdatesProcessor;
use Crustum\Speculum\Recording\RequestPathFilter;
use Crustum\Speculum\Recording\WorkerFlushPolicy;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Sanitizer\SensitiveData;
use Crustum\Speculum\Support\Avatar;
use Crustum\Speculum\Watcher\VarDumpWatcher;
use Exception;
use Throwable;

/**
 * Speculum recording facade: record, filter, tag, pause, flush.
 *
 * Watcher registration, path filters, worker flush, and SPA script vars live in
 * {@see WatcherRegistry}, {@see RequestPathFilter}, {@see WorkerFlushPolicy},
 * and {@see \Crustum\Speculum\Frontend\ScriptVariables}.
 */
class Speculum
{
    public const PAUSE_CACHE_KEY = 'speculum:pause-recording';

    /**
     * Per-entry filter callbacks.
     *
     * @var list<callable(\Crustum\Speculum\Entry\IncomingEntry): bool>
     */
    public static array $filterUsing = [];

    /**
     * Batch filter callbacks applied before store.
     *
     * @var list<callable(list<\Crustum\Speculum\Entry\IncomingEntry>): bool>
     */
    public static array $filterBatchUsing = [];

    /**
     * Hook invoked after recording starts for a request or console run.
     *
     * @var \Closure|null
     */
    public static ?Closure $afterRecordingHook = null;

    /**
     * Hooks invoked after entries are stored.
     *
     * @var list<\Closure>
     */
    public static array $afterStoringHooks = [];

    /**
     * Callbacks that add tags to incoming entries.
     *
     * @var list<\Closure>
     */
    public static array $tagUsing = [];

    /**
     * Pending entries waiting to be stored.
     *
     * @var list<\Crustum\Speculum\Entry\IncomingEntry>
     */
    public static array $entriesQueue = [];

    /**
     * Pending entry updates waiting to be stored.
     *
     * @var list<\Crustum\Speculum\Entry\EntryUpdate>
     */
    public static array $updatesQueue = [];

    /**
     * Active recording batch id (queue job window), or null for HTTP/CLI store-time batches.
     *
     * @var string|null
     */
    public static ?string $recordingBatchId = null;

    /**
     * Earliest known request start microtime, captured as early as possible
     * (plugin bootstrap / Application.buildContainer) so the recorded request
     * duration reflects full app bootstrap, not just middleware dispatch.
     *
     * @var float|null
     */
    private static ?float $requestStartedAt = null;

    /**
     * Request header names hidden from recorded request entries.
     *
     * @var list<string>
     */
    public static array $hiddenRequestHeaders = [
        'authorization',
        'proxy-authorization',
        'php-auth-pw',
        'cookie',
        'set-cookie',
        'x-xsrf-token',
        'x-csrf-token',
    ];

    /**
     * Request parameter key patterns (exact or glob: `*password*`, `*token*`).
     *
     * @var list<string>
     */
    public static array $hiddenRequestParameters = [
        '*password*',
        '*token*',
        '*secret*',
        '*api_key*',
        '*apikey*',
    ];

    /**
     * Response JSON key patterns (exact or glob).
     *
     * @var list<string>
     */
    public static array $hiddenResponseParameters = [
        '*password*',
        '*token*',
        '*secret*',
        '*api_key*',
        '*apikey*',
    ];

    /**
     * Model attribute key patterns redacted in recorded model `changes`.
     *
     * @var list<string>
     */
    public static array $hiddenModelAttributes = [
        '*password*',
        '*token*',
        '*secret*',
        '*api_key*',
        '*apikey*',
    ];

    /**
     * Whether framework events are ignored by default for event watchers.
     *
     * @var bool
     */
    public static bool $ignoreFrameworkEvents = true;

    /**
     * Whether Speculum is currently recording entries.
     *
     * @var bool
     */
    public static bool $shouldRecord = false;

    /**
     * Active entries repository instance.
     *
     * @var \Crustum\Speculum\Contract\EntriesRepository|null
     */
    protected static ?EntriesRepository $repository = null;

    /**
     * Authenticated identity attached to entries recorded in the current request.
     *
     * @var object|null
     */
    protected static ?object $user = null;

    /**
     * Set the entries repository used by Speculum.
     *
     * @param \Crustum\Speculum\Contract\EntriesRepository $repository Entries repository.
     * @return void
     */
    public static function setRepository(EntriesRepository $repository): void
    {
        static::$repository = $repository;
    }

    /**
     * Remember the authenticated user for entries recorded during this request.
     *
     * @param object|null $user Authenticated identity or user entity.
     * @return void
     */
    public static function auth(?object $user): void
    {
        static::$user = $user;
    }

    /**
     * Return the authenticated user currently attached to Speculum recording.
     *
     * @return object|null
     */
    public static function authenticatedUser(): ?object
    {
        return static::$user;
    }

    /**
     * Attach the current authenticated identity to an incoming entry.
     *
     * Prefers an identity set via auth(), then falls back to Router::getRequest().
     *
     * @param \Crustum\Speculum\Entry\IncomingEntry $entry Incoming entry.
     * @return void
     */
    protected static function applyAuthenticatedUser(IncomingEntry $entry): void
    {
        $user = static::$user;
        if ($user === null) {
            $identity = Router::getRequest()?->getAttribute('identity');
            if (is_object($identity)) {
                $user = $identity;
                static::$user = $identity;
            }
        }

        if ($user !== null) {
            $entry->user($user);
        }
    }

    /**
     * Return the configured entries repository.
     *
     * @return \Crustum\Speculum\Contract\EntriesRepository
     */
    public static function getRepository(): EntriesRepository
    {
        if (!static::$repository instanceof EntriesRepository) {
            throw new Exception('Speculum entries repository has not been set.');
        }

        return static::$repository;
    }

    /**
     * Capture the earliest request start time.
     *
     * Call this as early as possible (plugin bootstrap, Application.buildContainer)
     * so the logged request duration spans full app bootstrap. The first capture in
     * a request wins; pass `$force` to overwrite (used by plugin bootstrap, which
     * runs on every request and clears any stale value from a previous request).
     *
     * @param bool $force Overwrite an existing start time.
     * @return void
     */
    public static function markRequestStart(bool $force = false): void
    {
        if ($force || self::$requestStartedAt === null) {
            self::$requestStartedAt = microtime(true);
        }
    }

    /**
     * Earliest captured request start microtime, or null.
     *
     * @return float|null
     */
    public static function requestStartedAt(): ?float
    {
        return self::$requestStartedAt;
    }

    /**
     * Bootstrap watchers and start recording when appropriate.
     *
     * @return void
     */
    public static function start(): void
    {
        if (!Configure::read('Speculum.enabled', false)) {
            return;
        }

        SensitiveData::syncFromConfig();
        WatcherRegistry::registerConfigured();
        StorageListener::register();

        if (PHP_SAPI === 'cli') {
            if (RequestPathFilter::runningApprovedConsoleCommand()) {
                static::startRecording(false);
            }

            return;
        }

        static::startRecording(false);
    }

    /**
     * Start recording Speculum entries.
     *
     * @param bool $loadMonitoredTags Whether to load monitored tags.
     * @return void
     */
    public static function startRecording(bool $loadMonitoredTags = true): void
    {
        if ($loadMonitoredTags) {
            try {
                static::getRepository()->loadMonitoredTags();
            } catch (Throwable) {
            }
        }

        $recordingPaused = false;
        try {
            $recordingPaused = (bool)Cache::read(static::PAUSE_CACHE_KEY);
        } catch (Exception) {
        }

        static::$shouldRecord = !$recordingPaused;
    }

    /**
     * Stop recording Speculum entries.
     *
     * @return void
     */
    public static function stopRecording(): void
    {
        static::$shouldRecord = false;
    }

    /**
     * Set or clear the active recording batch id (queue job windows).
     *
     * @param string|null $batchId Batch UUID, or null when no job window is open.
     * @return void
     */
    public static function setRecordingBatchId(?string $batchId): void
    {
        static::$recordingBatchId = $batchId;
    }

    /**
     * Active recording batch id, if any.
     *
     * @return string|null
     */
    public static function recordingBatchId(): ?string
    {
        return static::$recordingBatchId;
    }

    /**
     * Pause recording across processes via cache flag.
     *
     * @return void
     */
    public static function pauseRecording(): void
    {
        try {
            Cache::write(static::PAUSE_CACHE_KEY, true);
        } catch (Throwable) {
        }

        static::stopRecording();
    }

    /**
     * Resume recording and clear the pause cache flag.
     *
     * @param bool $loadMonitoredTags Whether to load monitored tags.
     * @return void
     */
    public static function resumeRecording(bool $loadMonitoredTags = true): void
    {
        try {
            Cache::delete(static::PAUSE_CACHE_KEY);
        } catch (Throwable) {
        }

        static::startRecording($loadMonitoredTags);
    }

    /**
     * Determine whether recording is paused via the cache flag.
     *
     * @return bool
     */
    public static function isRecordingPaused(): bool
    {
        try {
            return (bool)Cache::read(static::PAUSE_CACHE_KEY);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Snapshot of Speculum control status for MCP / UI.
     *
     * @return array{
     *     enabled: bool,
     *     paused: bool,
     *     recording: bool,
     *     monitored_tags: list<string>,
     *     available_watchers: list<string>
     * }
     */
    public static function recordingStatus(): array
    {
        $monitoredTags = [];
        try {
            $monitoredTags = static::getRepository()->monitoring();
        } catch (Throwable) {
        }

        return [
            'enabled' => (bool)Configure::read('Speculum.enabled', false),
            'paused' => static::isRecordingPaused(),
            'recording' => static::isRecording(),
            'monitored_tags' => $monitoredTags,
            'available_watchers' => WatcherRegistry::availableWatchers(),
        ];
    }

    /**
     * Run a callback without recording Speculum entries.
     *
     * @param callable(): mixed $callback Callback to run without recording.
     * @return mixed
     */
    public static function withoutRecording(callable $callback): mixed
    {
        $shouldRecord = static::$shouldRecord;
        static::$shouldRecord = false;

        try {
            return $callback();
        } finally {
            static::$shouldRecord = $shouldRecord;
        }
    }

    /**
     * Determine whether Speculum is currently recording.
     *
     * @return bool
     */
    public static function isRecording(): bool
    {
        return static::$shouldRecord;
    }

    /**
     * Queue an incoming entry of the given type.
     *
     * @param string $type Entry type.
     * @param \Crustum\Speculum\Entry\IncomingEntry $entry Incoming entry.
     * @return void
     */
    protected static function record(string $type, IncomingEntry $entry): void
    {
        if (!static::isRecording()) {
            return;
        }

        try {
            static::applyAuthenticatedUser($entry);
        } catch (Throwable) {
        }

        $entry->type($type);

        if (static::$recordingBatchId !== null && static::$recordingBatchId !== '') {
            $entry->batchId(static::$recordingBatchId);
        }

        $tags = [];
        foreach (static::$tagUsing as $tagCallback) {
            $result = $tagCallback($entry);
            if (!is_array($result)) {
                continue;
            }

            foreach ($result as $tag) {
                if (is_string($tag)) {
                    $tags[] = $tag;
                }
            }
        }

        $entry->tags($tags);

        static::withoutRecording(function () use ($entry): void {
            $passes = array_all(static::$filterUsing, fn(callable $filter) => $filter($entry));
            if ($passes) {
                static::$entriesQueue[] = $entry;
            }

            if (static::$afterRecordingHook instanceof Closure) {
                (static::$afterRecordingHook)(new static(), $entry);
            }
        });
    }

    /**
     * Queue an update for an existing entry.
     *
     * @param \Crustum\Speculum\Entry\EntryUpdate $update Entry update.
     * @return void
     */
    public static function recordUpdate(EntryUpdate $update): void
    {
        if (static::$shouldRecord) {
            static::$updatesQueue[] = $update;
        }
    }

    /**
     * Capture values into Speculum with no output (requires VarDumpWatcher enabled).
     *
     * One call creates one entry (all values + file/line of the caller).
     *
     * @param mixed ...$values Values to capture.
     * @return void
     */
    public static function varDump(mixed ...$values): void
    {
        $watcher = VarDumpWatcher::instance();
        if (!$watcher instanceof VarDumpWatcher || $values === []) {
            return;
        }

        $watcher->recordValues(array_values($values));
    }

    /**
     * Queue an entry of the given type (core EntryType or extension string).
     *
     * @param \Crustum\Speculum\Enum\EntryType|string $type Entry type.
     * @param \Crustum\Speculum\Entry\IncomingEntry $entry Entry.
     * @return void
     */
    public static function recordEntry(EntryType|string $type, IncomingEntry $entry): void
    {
        static::record($type instanceof EntryType ? $type->value : $type, $entry);
    }

    /**
     * Discard all queued entries without storing them.
     *
     * @return void
     */
    public static function flushEntries(): void
    {
        static::$entriesQueue = [];
        static::$recordingBatchId = null;
        WorkerFlushPolicy::resetFlushWindow();
    }

    /**
     * Register an entry filter callback.
     *
     * @param \Closure $callback Filter callback.
     * @return static
     */
    public static function filter(Closure $callback): static
    {
        static::$filterUsing[] = $callback;

        return new static();
    }

    /**
     * Register a batch filter callback.
     *
     * @param \Closure $callback Batch filter callback.
     * @return static
     */
    public static function filterBatch(Closure $callback): static
    {
        static::$filterBatchUsing[] = $callback;

        return new static();
    }

    /**
     * Register a hook invoked after an entry is recorded.
     *
     * @param \Closure $callback After recording hook.
     * @return static
     */
    public static function afterRecording(Closure $callback): static
    {
        static::$afterRecordingHook = $callback;

        return new static();
    }

    /**
     * Register a hook invoked after entries are stored.
     *
     * @param \Closure $callback After storing hook.
     * @return static
     */
    public static function afterStoring(Closure $callback): static
    {
        static::$afterStoringHooks[] = $callback;

        return new static();
    }

    /**
     * Register a callback that contributes tags to entries.
     *
     * @param \Closure $callback Tag callback.
     * @return static
     */
    public static function tag(Closure $callback): static
    {
        static::$tagUsing[] = $callback;

        return new static();
    }

    /**
     * Store queued entries and flush queues.
     *
     * @param \Crustum\Speculum\Contract\EntriesRepository|null $storage Optional repository.
     * @return void
     */
    public static function store(?EntriesRepository $storage = null): void
    {
        $storage ??= static::getRepository();

        if (static::$entriesQueue === [] && static::$updatesQueue === []) {
            return;
        }

        $stored = false;
        static::withoutRecording(function () use ($storage, &$stored): void {
            $entries = static::$entriesQueue;
            foreach (static::$filterBatchUsing as $filter) {
                if (!$filter($entries)) {
                    static::flushEntries();
                    $stored = true;

                    return;
                }
            }

            try {
                $batchId = Text::uuid();
                $collected = static::collectEntries($batchId);
                $storage->store($collected);

                $pendingUpdates = $storage->update(static::collectUpdates($batchId));
                if ($pendingUpdates !== []) {
                    static::dispatchPendingUpdates($pendingUpdates);
                }

                if ($storage instanceof TerminableRepository) {
                    $storage->terminate();
                }

                foreach (static::$afterStoringHooks as $hook) {
                    $hook($collected, $batchId);
                }

                $stored = true;
            } catch (Throwable $throwable) {
                Log::error('Speculum store failed: ' . $throwable->getMessage(), [
                    'exception_class' => $throwable::class,
                    'exception_message' => $throwable->getMessage(),
                ]);
            }
        });

        if ($stored) {
            static::$entriesQueue = [];
            static::$updatesQueue = [];
            WorkerFlushPolicy::resetFlushWindow();
        }
    }

    /**
     * Queue a job that retries failed entry updates.
     *
     * @param list<\Crustum\Speculum\Entry\EntryUpdate> $pendingUpdates Failed updates to retry.
     * @return void
     */
    protected static function dispatchPendingUpdates(array $pendingUpdates): void
    {
        JobDispatcher::push([
            'pendingUpdates' => PendingUpdatesProcessor::serializeUpdates($pendingUpdates),
            'attempt' => 0,
        ], PendingUpdatesProcessor::defaultPushOptions());
    }

    /**
     * Assign the batch identifier to queued entries.
     *
     * @param string $batchId Batch UUID.
     * @return list<\Crustum\Speculum\Entry\IncomingEntry>
     */
    protected static function collectEntries(string $batchId): array
    {
        foreach (static::$entriesQueue as $entry) {
            if ($entry->batchId === null || $entry->batchId === '') {
                $entry->batchId($batchId);
            }

            if ($entry instanceof IncomingVarDumpEntry) {
                $entry->assignEntryPoint(static::$entriesQueue);
            }
        }

        return static::$entriesQueue;
    }

    /**
     * Attach the batch identifier to queued entry updates.
     *
     * Prefers the matching entry's batch when entries already carry a job batch id.
     *
     * @param string $batchId Fallback batch UUID.
     * @return list<\Crustum\Speculum\Entry\EntryUpdate>
     */
    protected static function collectUpdates(string $batchId): array
    {
        $batchesByUuid = [];
        foreach (static::$entriesQueue as $entry) {
            if ($entry->batchId !== null && $entry->batchId !== '') {
                $batchesByUuid[$entry->uuid] = $entry->batchId;
            }
        }

        foreach (static::$updatesQueue as $entry) {
            $entry->change([
                'updated_batch_id' => $batchesByUuid[$entry->uuid] ?? $batchId,
            ]);
        }

        return static::$updatesQueue;
    }

    /**
     * Hide sensitive request headers from recorded entries.
     *
     * @param list<string> $headers Headers to hide.
     * @return static
     */
    public static function hideRequestHeaders(array $headers): static
    {
        static::$hiddenRequestHeaders = array_values(array_unique(array_merge(
            static::$hiddenRequestHeaders,
            $headers,
        )));

        return new static();
    }

    /**
     * Hide sensitive request parameters from recorded entries.
     *
     * @param list<string> $attributes Parameters to hide.
     * @return static
     */
    public static function hideRequestParameters(array $attributes): static
    {
        static::$hiddenRequestParameters = array_merge(
            static::$hiddenRequestParameters,
            $attributes,
        );

        return new static();
    }

    /**
     * Hide sensitive response parameters from recorded entries.
     *
     * @param list<string> $attributes Parameters to hide.
     * @return static
     */
    public static function hideResponseParameters(array $attributes): static
    {
        static::$hiddenResponseParameters = array_values(array_unique(array_merge(
            static::$hiddenResponseParameters,
            $attributes,
        )));

        return new static();
    }

    /**
     * Hide sensitive model attributes from recorded model change payloads.
     *
     * @param list<string> $attributes Attributes to hide.
     * @return static
     */
    public static function hideModelAttributes(array $attributes): static
    {
        static::$hiddenModelAttributes = array_values(array_unique(array_merge(
            static::$hiddenModelAttributes,
            $attributes,
        )));

        return new static();
    }

    /**
     * Include framework-fired events when recording events.
     *
     * @return static
     */
    public static function recordFrameworkEvents(): static
    {
        static::$ignoreFrameworkEvents = false;

        return new static();
    }

    /**
     * Register a custom avatar URL callback.
     *
     * @param \Closure $callback Avatar callback.
     * @return static
     */
    public static function avatar(Closure $callback): static
    {
        Avatar::register($callback);

        return new static();
    }
}
