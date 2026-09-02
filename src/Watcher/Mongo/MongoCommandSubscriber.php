<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher\Mongo;

use MongoDB\Driver\Monitoring\CommandFailedEvent;
use MongoDB\Driver\Monitoring\CommandStartedEvent;
use MongoDB\Driver\Monitoring\CommandSubscriber;
use MongoDB\Driver\Monitoring\CommandSucceededEvent;
use Throwable;

/**
 * ext-mongodb APM bridge for MongoWatcher.
 *
 * Loaded only when SoftFeature::Mongo is available (real or stub CommandSubscriber).
 */
class MongoCommandSubscriber implements CommandSubscriber
{
    /**
     * Command start times.
     *
     * @var array<int|string, float>
     */
    protected array $startedAt = [];

    /**
     * Command metadata.
     *
     * @var array<int|string, array{command: string, database: ?string, collection: ?string, payload: array<string, mixed>}>
     */
    protected array $startedMeta = [];

    /**
     * Parent watcher.
     *
     * @param \Crustum\Speculum\Watcher\Mongo\MongoWatcher $watcher Parent watcher.
     */
    public function __construct(protected MongoWatcher $watcher)
    {
    }

    /**
     * Handle command started event.
     *
     * @param \MongoDB\Driver\Monitoring\CommandStartedEvent $event Event.
     * @return void
     */
    public function commandStarted(CommandStartedEvent $event): void
    {
        $requestId = $event->getRequestId();
        $commandName = strtolower($event->getCommandName());
        if ($this->watcher->shouldIgnore($commandName)) {
            return;
        }

        $command = $this->commandToArray($event->getCommand());
        $database = $this->extractDatabase($event, $command);
        $collection = $this->extractCollection($commandName, $command);

        $this->startedAt[$requestId] = microtime(true);
        $this->startedMeta[$requestId] = [
            'command' => $commandName,
            'database' => $database,
            'collection' => $collection,
            'payload' => $this->truncatePayload($command),
        ];
    }

    /**
     * Handle command succeeded event.
     *
     * @param \MongoDB\Driver\Monitoring\CommandSucceededEvent $event Event.
     * @return void
     */
    public function commandSucceeded(CommandSucceededEvent $event): void
    {
        $requestId = $event->getRequestId();
        $meta = $this->startedMeta[$requestId] ?? null;
        $started = $this->startedAt[$requestId] ?? null;
        unset($this->startedAt[$requestId], $this->startedMeta[$requestId]);

        if ($meta === null || $started === null) {
            return;
        }

        $durationMs = max(0, (microtime(true) - $started) * 1000);
        $this->watcher->record(
            $meta['command'],
            $durationMs,
            $meta['database'],
            $meta['collection'],
            $meta['payload'],
            false,
        );
    }

    /**
     * Handle command failed event.
     *
     * @param \MongoDB\Driver\Monitoring\CommandFailedEvent $event Event.
     * @return void
     */
    public function commandFailed(CommandFailedEvent $event): void
    {
        $requestId = $event->getRequestId();
        $meta = $this->startedMeta[$requestId] ?? null;
        $started = $this->startedAt[$requestId] ?? null;
        unset($this->startedAt[$requestId], $this->startedMeta[$requestId]);

        if ($meta === null || $started === null) {
            return;
        }

        $durationMs = max(0, (microtime(true) - $started) * 1000);
        $payload = $meta['payload'];
        $payload['error'] = $event->getError()->getMessage();
        $this->watcher->record(
            $meta['command'],
            $durationMs,
            $meta['database'],
            $meta['collection'],
            $payload,
            true,
        );
    }

    /**
     * Convert command document to array.
     *
     * @param object|array<string, mixed> $command Command document.
     * @return array<string, mixed>
     */
    protected function commandToArray(object|array $command): array
    {
        if (is_array($command)) {
            return $command;
        }

        $encoded = json_encode($command);
        if (!is_string($encoded)) {
            return [];
        }

        $decoded = json_decode($encoded, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Extract database name from command event or document.
     *
     * @param \MongoDB\Driver\Monitoring\CommandStartedEvent $event Event.
     * @param array<string, mixed> $command Command array.
     * @return string|null
     */
    protected function extractDatabase(CommandStartedEvent $event, array $command): ?string
    {
        try {
            $name = $event->getDatabaseName();
            if ($name !== '') {
                return $name;
            }
        } catch (Throwable) {
        }

        $db = $command['$db'] ?? null;

        return is_string($db) && $db !== '' ? $db : null;
    }

    /**
     * Extract collection name from command document.
     *
     * @param string $commandName Command name.
     * @param array<string, mixed> $command Command array.
     * @return string|null
     */
    protected function extractCollection(string $commandName, array $command): ?string
    {
        $value = $command[$commandName] ?? $command['collection'] ?? null;
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    /**
     * Truncate payload to 64KB.
     *
     * @param array<string, mixed> $payload Payload.
     * @return array<string, mixed>
     */
    protected function truncatePayload(array $payload): array
    {
        $encoded = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($encoded)) {
            return [];
        }

        if (strlen($encoded) <= 65536) {
            return $payload;
        }

        return [
            '_truncated' => true,
            '_bytes' => strlen($encoded),
            'command' => $payload[array_key_first($payload)] ?? null,
        ];
    }
}
