<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Entry;

use Cake\Utility\Text;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;

/**
 * Fixture factories for inspection (list/show) tests.
 */
trait CreatesSpeculumEntriesTrait
{
    /**
     * Store an entry and return its UUID.
     *
     * @param string $type Entry type value.
     * @param array<string, mixed> $content Entry content.
     * @param string|null $batchId Batch UUID.
     * @param string|null $uuid Entry UUID.
     * @param list<string> $tags Entry tags.
     * @return string
     */
    protected function storeEntry(
        string $type,
        array $content,
        ?string $batchId = null,
        ?string $uuid = null,
        array $tags = [],
    ): string {
        $entry = IncomingEntry::make($content, $uuid)
            ->type($type)
            ->batchId($batchId ?? Text::uuid());
        if ($tags !== []) {
            $entry->tags($tags);
        }

        $this->repository->store([$entry]);

        return $entry->uuid;
    }

    /**
     * Store a request entry and return its UUID.
     *
     * @param array<string, mixed> $content Content overrides.
     * @param string|null $batchId Batch UUID.
     * @param string|null $uuid Entry UUID.
     * @param list<string> $tags Entry tags.
     * @return string
     */
    protected function createRequest(
        array $content = [],
        ?string $batchId = null,
        ?string $uuid = null,
        array $tags = [],
    ): string {
        return $this->storeEntry(EntryType::Request->value, $content + [
            'method' => 'GET',
            'uri' => '/test',
            'response_status' => 200,
            'duration' => 50,
            'memory' => 8,
            'ip_address' => '127.0.0.1',
            'payload' => [],
            'response' => [],
        ], $batchId, $uuid, $tags);
    }

    /**
     * Store a query entry and return its UUID.
     *
     * @param array<string, mixed> $content Content overrides.
     * @param string|null $batchId Batch UUID.
     * @param string|null $uuid Entry UUID.
     * @return string
     */
    protected function createQuery(
        array $content = [],
        ?string $batchId = null,
        ?string $uuid = null,
    ): string {
        return $this->storeEntry(EntryType::Query->value, $content + [
            'sql' => 'select 1',
            'time' => 1.5,
            'connection' => 'test',
            'slow' => false,
            'file' => 'query.php',
            'line' => 10,
            'bindings' => [],
        ], $batchId, $uuid);
    }

    /**
     * Store an exception entry and return its UUID.
     *
     * @param array<string, mixed> $content Content overrides.
     * @param string|null $batchId Batch UUID.
     * @param string|null $uuid Entry UUID.
     * @return string
     */
    protected function createException(
        array $content = [],
        ?string $batchId = null,
        ?string $uuid = null,
    ): string {
        return $this->storeEntry(EntryType::Exception->value, $content + [
            'class' => 'RuntimeException',
            'message' => 'Test failure',
            'file' => 'test.php',
            'line' => 1,
            'trace' => [],
        ], $batchId, $uuid);
    }

    /**
     * Store a job entry and return its UUID.
     *
     * @param array<string, mixed> $content Content overrides.
     * @param string|null $batchId Batch UUID.
     * @param string|null $uuid Entry UUID.
     * @return string
     */
    protected function createJob(
        array $content = [],
        ?string $batchId = null,
        ?string $uuid = null,
    ): string {
        return $this->storeEntry(EntryType::Job->value, $content + [
            'name' => 'App\\Job\\DemoJob',
            'queue' => 'default',
            'connection' => 'default',
            'status' => 'processed',
            'data' => [],
        ], $batchId, $uuid);
    }

    /**
     * Store a log entry and return its UUID.
     *
     * @param array<string, mixed> $content Content overrides.
     * @param string|null $batchId Batch UUID.
     * @param string|null $uuid Entry UUID.
     * @return string
     */
    protected function createLog(
        array $content = [],
        ?string $batchId = null,
        ?string $uuid = null,
    ): string {
        return $this->storeEntry(EntryType::Log->value, $content + [
            'level' => 'info',
            'message' => 'Test log message',
            'context' => [],
        ], $batchId, $uuid);
    }

    /**
     * Store a cache entry and return its UUID.
     *
     * @param array<string, mixed> $content Content overrides.
     * @param string|null $batchId Batch UUID.
     * @param string|null $uuid Entry UUID.
     * @return string
     */
    protected function createCache(
        array $content = [],
        ?string $batchId = null,
        ?string $uuid = null,
    ): string {
        return $this->storeEntry(EntryType::Cache->value, $content + [
            'type' => 'hit',
            'key' => 'test-key',
            'value' => 'test-value',
        ], $batchId, $uuid);
    }

    /**
     * Store a mail entry and return its UUID.
     *
     * @param array<string, mixed> $content Content overrides.
     * @param string|null $batchId Batch UUID.
     * @param string|null $uuid Entry UUID.
     * @return string
     */
    protected function createMail(
        array $content = [],
        ?string $batchId = null,
        ?string $uuid = null,
    ): string {
        return $this->storeEntry(EntryType::Mail->value, $content + [
            'mailable' => 'App\\Mail\\WelcomeMail',
            'subject' => 'Welcome',
            'queued' => false,
            'from' => ['noreply@example.com' => null],
            'to' => ['alice@example.com' => 'Alice'],
        ], $batchId, $uuid);
    }

    /**
     * Store an event entry and return its UUID.
     *
     * @param array<string, mixed> $content Content overrides.
     * @param string|null $batchId Batch UUID.
     * @param string|null $uuid Entry UUID.
     * @return string
     */
    protected function createEvent(
        array $content = [],
        ?string $batchId = null,
        ?string $uuid = null,
    ): string {
        return $this->storeEntry(EntryType::Event->value, $content + [
            'name' => 'App\\Event\\OrderCreated',
            'payload' => ['order' => 1],
            'broadcast' => false,
        ], $batchId, $uuid);
    }
}
