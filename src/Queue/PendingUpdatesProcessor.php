<?php
declare(strict_types=1);

namespace Crustum\Speculum\Queue;

use Cake\Core\Configure;
use Crustum\Speculum\Entry\EntryUpdate;
use Crustum\Speculum\Speculum;

/**
 * Shared pending-update apply + requeue logic for all Speculum queue backends.
 */
class PendingUpdatesProcessor
{
    /**
     * Apply pending entry updates and requeue failures when attempts remain.
     *
     * @param array<mixed> $pending Serialized pending updates.
     * @param int $attempt Current attempt number (0-based from producer).
     * @return list<\Crustum\Speculum\Entry\EntryUpdate> Failed updates after this attempt.
     */
    public function process(array $pending, int $attempt): array
    {
        $nextAttempt = $attempt + 1;
        $updates = $this->hydrateUpdates($pending);
        $failed = Speculum::getRepository()->update($updates);

        if ($failed !== [] && $nextAttempt < 3) {
            $this->requeueFailed($failed, $nextAttempt);
        }

        return $failed;
    }

    /**
     * Build push options from Speculum queue config.
     *
     * @return array<string, mixed>
     */
    public static function defaultPushOptions(): array
    {
        $options = [
            'config' => (string)Configure::read('Speculum.queue.connection', 'default'),
        ];
        $queue = Configure::read('Speculum.queue.queue');
        if (is_string($queue) && $queue !== '') {
            $options['queue'] = $queue;
        }

        $delay = Configure::read('Speculum.queue.delay', 10);
        if (is_numeric($delay) && (int)$delay > 0) {
            $options['delay'] = (int)$delay;
        }

        return $options;
    }

    /**
     * Serialize EntryUpdate list for queue payloads.
     *
     * @param list<\Crustum\Speculum\Entry\EntryUpdate> $updates Updates.
     * @return list<array{uuid: string, type: string, changes: array<string, mixed>, tagsChanges: array<string, mixed>}>
     */
    public static function serializeUpdates(array $updates): array
    {
        return array_map(static fn(EntryUpdate $update): array => [
            'uuid' => $update->uuid,
            'type' => $update->type,
            'changes' => $update->changes,
            'tagsChanges' => $update->tagsChanges,
        ], $updates);
    }

    /**
     * @param list<\Crustum\Speculum\Entry\EntryUpdate> $failed Failed updates.
     * @param int $attempt Attempt number to store on the requeued job.
     * @return void
     */
    protected function requeueFailed(array $failed, int $attempt): void
    {
        JobDispatcher::push([
            'pendingUpdates' => static::serializeUpdates($failed),
            'attempt' => $attempt,
        ], static::defaultPushOptions());
    }

    /**
     * @param array<mixed> $pending Serialized rows.
     * @return list<\Crustum\Speculum\Entry\EntryUpdate>
     */
    protected function hydrateUpdates(array $pending): array
    {
        $updates = [];
        foreach ($pending as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (empty($row['uuid'])) {
                continue;
            }

            if (empty($row['type'])) {
                continue;
            }

            $update = new EntryUpdate(
                (string)$row['uuid'],
                (string)$row['type'],
                is_array($row['changes'] ?? null) ? $row['changes'] : [],
            );
            if (isset($row['tagsChanges']) && is_array($row['tagsChanges'])) {
                $update->tagsChanges = $row['tagsChanges'] + ['removed' => [], 'added' => []];
            }

            $updates[] = $update;
        }

        return $updates;
    }
}
