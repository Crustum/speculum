<?php
declare(strict_types=1);

namespace Crustum\Speculum\Recording;

use Cake\Core\Configure;
use Cake\Utility\Text;
use Crustum\Speculum\Speculum;

/**
 * Mid-job recording windows and timed/limit flush for queue workers.
 */
final class WorkerFlushPolicy
{
    /**
     * Nested markers for active queued-job processing windows.
     *
     * @var list<true>
     */
    private static array $processingJobs = [];

    /**
     * Nested Speculum batch ids for each open job window.
     *
     * @var list<string>
     */
    private static array $batchStack = [];

    /**
     * Wall-clock start of the current worker flush window, or null when idle.
     *
     * @var float|null
     */
    private static ?float $lastFlushAt = null;

    /**
     * Begin a queued-job recording window (cakephp/queue or Queuesadilla worker).
     *
     * Each job gets its own Speculum `batch_id` so Related stays per-job even when
     * storage flush is deferred across jobs.
     *
     * @return void
     */
    public static function begin(): void
    {
        Speculum::startRecording();
        $batchId = Text::uuid();
        self::$batchStack[] = $batchId;
        Speculum::setRecordingBatchId($batchId);
        self::$processingJobs[] = true;

        self::$lastFlushAt ??= microtime(true);
    }

    /**
     * End a queued-job recording window and optionally persist the entry queue.
     *
     * @param bool $persist Whether to consider flushing storage when the job stack is empty.
     * @return void
     */
    public static function end(bool $persist = true): void
    {
        array_pop(self::$processingJobs);
        array_pop(self::$batchStack);
        $parentBatch = self::$batchStack === []
            ? null
            : self::$batchStack[array_key_last(self::$batchStack)];
        Speculum::setRecordingBatchId($parentBatch);

        if ($persist && self::$processingJobs === []) {
            Speculum::stopRecording();
            self::maybeStore();
        }
    }

    /**
     * Clear the flush window clock after a successful store or discard.
     *
     * @return void
     */
    public static function resetFlushWindow(): void
    {
        self::$lastFlushAt = null;
    }

    /**
     * Reset nested job markers and flush clock (tests / process teardown).
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$processingJobs = [];
        self::$batchStack = [];
        self::$lastFlushAt = null;
        Speculum::setRecordingBatchId(null);
    }

    /**
     * Persist buffered worker entries when interval or limit is met.
     *
     * Interval `0` (or less) stores immediately. Otherwise stores when pending
     * entries+updates reach `worker_flush_limit`, or when the flush window age
     * reaches `worker_flush_interval` seconds. HTTP terminate, Command.afterExecute,
     * and shutdown still call {@see Speculum::store()} directly (force flush).
     *
     * Deferred flush may store several jobs in one write, but each job keeps its
     * own `batch_id` assigned at {@see begin()}.
     *
     * @return void
     */
    public static function maybeStore(): void
    {
        if (Speculum::$entriesQueue === [] && Speculum::$updatesQueue === []) {
            return;
        }

        $interval = (float)Configure::read('Speculum.queue.worker_flush_interval', 1.0);
        if ($interval <= 0.0) {
            Speculum::store();
            self::$lastFlushAt = null;

            return;
        }

        $limit = (int)Configure::read('Speculum.queue.worker_flush_limit', 2000);
        $pending = count(Speculum::$entriesQueue) + count(Speculum::$updatesQueue);
        if ($limit > 0 && $pending >= $limit) {
            Speculum::store();
            self::$lastFlushAt = null;

            return;
        }

        $now = microtime(true);
        if (self::$lastFlushAt === null) {
            self::$lastFlushAt = $now;

            return;
        }

        if ($now - self::$lastFlushAt >= $interval) {
            Speculum::store();
            self::$lastFlushAt = null;
        }
    }
}
