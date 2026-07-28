<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\I18n\DateTime;
use Crustum\BatchQueue\Data\BatchDefinition;
use Crustum\BatchQueue\Event\BatchFinished;
use Crustum\BatchQueue\Event\BatchStarted;
use Crustum\Speculum\Entry\EntryUpdate;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use DateTimeInterface;

/**
 * Soft watcher for Crustum BatchQueue (`BatchQueue.BatchStarted` / `BatchFinished`).
 */
class BatchWatcher extends Watcher
{
    /**
     * @inheritDoc
     */
    public function register(): void
    {
        if (!WatcherRegistry::isSoftAvailable(SoftFeature::Batch)) {
            return;
        }

        EventManager::instance()->on(BatchStarted::NAME, function (EventInterface $event): void {
            $this->recordStarted($event);
        });

        EventManager::instance()->on(BatchFinished::NAME, function (EventInterface $event): void {
            $this->recordFinished($event);
        });
    }

    /**
     * Record a batch that has started.
     *
     * @param \Cake\Event\EventInterface<object> $event Batch started event.
     * @return void
     */
    public function recordStarted(EventInterface $event): void
    {
        $batch = $this->resolveBatch($event);
        if (!$batch instanceof BatchDefinition || !Speculum::isRecording()) {
            return;
        }

        $content = $this->formatBatch($batch);
        Speculum::recordEntry(
            EntryType::Batch,
            IncomingEntry::make($content, $batch->id)
                ->withFamilyHash($batch->id)
                ->tags([$batch->id]),
        );
    }

    /**
     * Update a batch entry when the batch finishes.
     *
     * @param \Cake\Event\EventInterface<object> $event Batch finished event.
     * @return void
     */
    public function recordFinished(EventInterface $event): void
    {
        $batch = $this->resolveBatch($event);
        if (!$batch instanceof BatchDefinition || !Speculum::isRecording()) {
            return;
        }

        $content = $this->formatBatch($batch);
        Speculum::recordUpdate(EntryUpdate::make($batch->id, EntryType::Batch, $content));
    }

    /**
     * Record a batch from a loosely typed event payload.
     *
     * @param string $event Event name.
     * @param array<string, mixed> $data Event data.
     * @return void
     */
    public function record(string $event, array $data): void
    {
        if (!Speculum::isRecording()) {
            return;
        }

        $batch = $data['batch'] ?? null;
        if ($batch instanceof BatchDefinition) {
            $content = $this->formatBatch($batch);
            if (str_contains(strtolower($event), 'finished')) {
                Speculum::recordUpdate(EntryUpdate::make($batch->id, EntryType::Batch, $content));

                return;
            }

            Speculum::recordEntry(
                EntryType::Batch,
                IncomingEntry::make($content, $batch->id)->withFamilyHash($batch->id),
            );

            return;
        }

        $id = (string)($data['id'] ?? $data['batch_id'] ?? '');
        if ($id === '') {
            return;
        }

        $total = (int)($data['totalJobs'] ?? $data['total_jobs'] ?? 0);
        $failed = (int)($data['failedJobs'] ?? $data['failed_jobs'] ?? 0);
        $pending = (int)($data['pendingJobs'] ?? $data['pending_jobs'] ?? max(0, $total - $failed));
        $completed = max(0, $total - $pending - $failed);
        $progress = $total > 0 ? (int)round($completed / $total * 100) : 0;

        Speculum::recordEntry(EntryType::Batch, IncomingEntry::make([
            'id' => $id,
            'name' => $data['name'] ?? $id,
            'totalJobs' => $total,
            'pendingJobs' => $pending,
            'failedJobs' => $failed,
            'progress' => $progress,
            'connection' => $data['connection'] ?? 'default',
            'queue' => $data['queue'] ?? 'default',
            'finishedAt' => str_contains(strtolower($event), 'finished')
                ? DateTime::now()->format(DateTimeInterface::ATOM)
                : null,
        ], $id)->withFamilyHash($id));
    }

    /**
     * Resolve a BatchDefinition from a Cake event.
     *
     * @param \Cake\Event\EventInterface<object> $event Event.
     * @return \Crustum\BatchQueue\Data\BatchDefinition|null
     */
    protected function resolveBatch(EventInterface $event): ?BatchDefinition
    {
        if ($event instanceof BatchStarted || $event instanceof BatchFinished) {
            return $event->getBatch();
        }

        $subject = $event->getSubject();
        if ($subject instanceof BatchDefinition) {
            return $subject;
        }

        $batch = $event->getData('batch');

        return $batch instanceof BatchDefinition ? $batch : null;
    }

    /**
     * Format a BatchDefinition for Speculum storage.
     *
     * @param \Crustum\BatchQueue\Data\BatchDefinition $batch Batch definition.
     * @return array<string, mixed>
     */
    protected function formatBatch(BatchDefinition $batch): array
    {
        $pending = max(0, $batch->totalJobs - $batch->completedJobs - $batch->failedJobs);
        $progress = $batch->totalJobs > 0
            ? (int)round($batch->completedJobs / $batch->totalJobs * 100)
            : 0;

        $name = $batch->options['name'] ?? $batch->id;

        return [
            'id' => $batch->id,
            'name' => is_string($name) ? $name : $batch->id,
            'totalJobs' => $batch->totalJobs,
            'pendingJobs' => $pending,
            'failedJobs' => $batch->failedJobs,
            'progress' => $progress,
            'connection' => $batch->queueConfig ?? 'default',
            'queue' => $batch->queueName ?? 'default',
            'status' => $batch->status,
            'type' => $batch->type,
            'allowsFailures' => (bool)($batch->options['allowFailures'] ?? $batch->options['allowsFailures'] ?? false),
            'finished' => $batch->completedAt?->format(DateTimeInterface::ATOM),
            'created' => $batch->created?->format(DateTimeInterface::ATOM),
        ];
    }
}
