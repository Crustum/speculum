<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\Integration;

use Cake\Utility\Text;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Test\TestCase\IntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * API route tests for shipped resources.
 */
class RouteTest extends IntegrationTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function speculumIndexRoutesProvider(): array
    {
        return [
            'Mail' => ['/speculum/api/mail', EntryType::Mail->value],
            'Exceptions' => ['/speculum/api/exceptions', EntryType::Exception->value],
            'Logs' => ['/speculum/api/logs', EntryType::Log->value],
            'Notifications' => ['/speculum/api/notifications', EntryType::Notification->value],
            'Jobs' => ['/speculum/api/jobs', EntryType::Job->value],
            'Batches' => ['/speculum/api/batches', EntryType::Batch->value],
            'Broadcasts' => ['/speculum/api/broadcasts', EntryType::Broadcast->value],
            'Events' => ['/speculum/api/events', EntryType::Event->value],
            'Cache' => ['/speculum/api/cache', EntryType::Cache->value],
            'Queries' => ['/speculum/api/queries', EntryType::Query->value],
            'Models' => ['/speculum/api/models', EntryType::Model->value],
            'Request' => ['/speculum/api/requests', EntryType::Request->value],
            'Views' => ['/speculum/api/views', EntryType::View->value],
            'Commands' => ['/speculum/api/commands', EntryType::Command->value],
            'Schedule' => ['/speculum/api/schedule', EntryType::ScheduledTask->value],
            'HTTP Clients' => ['/speculum/api/http-clients', EntryType::HttpClient->value],
        ];
    }

    /**
     * @param string $endpoint API endpoint.
     * @param string $entryType Entry type.
     * @return void
     */
    #[DataProvider('speculumIndexRoutesProvider')]
    public function testRoute(string $endpoint, string $entryType): void
    {
        $this->disableErrorHandlerMiddleware();
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->post($endpoint, ['take' => 10]);
        $this->assertResponseOk();
        $body = $this->jsonBody();
        $this->assertArrayHasKey('entries', $body);
        $this->assertIsArray($body['entries']);
    }

    /**
     * @param string $endpoint API endpoint.
     * @param string $entryType Entry type.
     * @return void
     */
    #[DataProvider('speculumIndexRoutesProvider')]
    public function testSimpleListOfEntries(string $endpoint, string $entryType): void
    {
        $entry = IncomingEntry::make($this->sampleContent($entryType))
            ->type($entryType)
            ->batchId(Text::uuid());
        $this->repository->store([$entry]);

        $this->disableErrorHandlerMiddleware();
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->post($endpoint, ['take' => 10]);
        $this->assertResponseOk();
        $body = $this->jsonBody();
        $this->assertNotEmpty($body['entries']);
        $this->assertSame($entry->uuid, $body['entries'][0]['id']);
        $this->assertSame($entryType, $body['entries'][0]['type']);
        $this->assertSame($entry->batchId, $body['entries'][0]['batch_id']);
    }

    /**
     * @param string $type Entry type.
     * @return array<string, mixed>
     */
    protected function sampleContent(string $type): array
    {
        return match ($type) {
            EntryType::Request->value => ['uri' => '/demo', 'method' => 'GET', 'response_status' => 200],
            EntryType::Log->value => ['level' => 'error', 'message' => 'demo'],
            EntryType::Query->value => ['sql' => 'select 1', 'time' => '1.00', 'slow' => false],
            EntryType::Cache->value => ['type' => 'hit', 'key' => 'k', 'value' => 1],
            EntryType::Command->value => ['command' => 'demo', 'exit_code' => 0],
            EntryType::Mail->value => ['subject' => 'Hi', 'mailable' => '', 'queued' => false],
            EntryType::Exception->value => ['class' => RuntimeException::class, 'message' => 'x', 'file' => __FILE__, 'line' => 1],
            EntryType::Job->value => ['status' => 'pending', 'name' => 'Demo'],
            EntryType::Event->value => ['name' => 'App.Demo', 'payload' => null],
            EntryType::Model->value => ['action' => 'updated', 'model' => 'Demo'],
            EntryType::View->value => ['name' => 'welcome', 'path' => '/welcome.php'],
            EntryType::HttpClient->value => ['method' => 'GET', 'uri' => 'https://example.com'],
            EntryType::Notification->value => ['channel' => 'mail', 'status' => 'sent'],
            EntryType::Batch->value => ['id' => Text::uuid(), 'name' => 'demo'],
            EntryType::Broadcast->value => ['event' => 'DemoEvent', 'channels' => ['demo'], 'connection' => 'default'],
            EntryType::ScheduledTask->value => ['command' => 'demo', 'exit_code' => 0],
            default => ['demo' => true],
        };
    }
}
