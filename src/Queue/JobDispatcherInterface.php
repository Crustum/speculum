<?php
declare(strict_types=1);

namespace Crustum\Speculum\Queue;

/**
 * Dispatches Speculum's own queue payloads to a host backend.
 */
interface JobDispatcherInterface
{
    /**
     * Whether this dispatcher can push jobs in the current environment.
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Push a Speculum internal job payload.
     *
     * @param array{pendingUpdates?: list<array<string, mixed>>, attempt?: int}|array<string, mixed> $data Job data.
     * @param array<string, mixed> $options Transport options (`config`, `queue`, `delay`, …).
     * @return void
     */
    public function push(array $data, array $options = []): void;
}
