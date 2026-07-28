<?php
declare(strict_types=1);

namespace Queue\Queue;

/**
 * Minimal stub of Dereuromark Queue Task for Speculum phpstan / offline analysis.
 *
 * Replaced at runtime when dereuromark/cakephp-queue is installed.
 */
abstract class Task
{
    /**
     * @param array<string, mixed> $data Task data.
     * @param int $jobId Queued job id.
     * @return void
     */
    abstract public function run(array $data, int $jobId): void;

    /**
     * @return string|null
     */
    public function description(): ?string
    {
        return null;
    }
}
