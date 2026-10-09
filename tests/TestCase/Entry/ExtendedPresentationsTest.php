<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Entry;

use Crustum\Speculum\Entry\EntryResult;
use Crustum\Speculum\Entry\Presentation\PresentationRegistry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Shape coverage for presentations.
 */
class ExtendedPresentationsTest extends TestCaseBase
{
    /**
     * @return void
     */
    #[DataProvider('summaryProvider')]
    public function testSummarizeContainsTypeSignal(string $type, array $content, string $expected): void
    {
        $summary = PresentationRegistry::for($type)->summarize($this->makeResult($type, $content));

        $this->assertStringContainsString($expected, $summary);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function summaryProvider(): array
    {
        return [
            'ai' => [EntryType::Ai->value, [
                'category' => 'tool',
                'name' => 'Ai.promptingAgent',
                'provider' => 'OpenAI',
                'model' => 'gpt-5',
                'duration' => 120,
            ], '[tool] Ai.promptingAgent'],
            'authorization' => [EntryType::Authorization->value, [
                'ability' => 'Posts/view',
                'allowed' => true,
            ], 'Posts/view (Allowed)'],
            'cakedc_auth' => [EntryType::CakeDCAuth->value, [
                'ability' => 'Posts/view',
                'allowed' => false,
            ], 'Posts/view (Denied)'],
            'batch' => [EntryType::Batch->value, [
                'name' => 'Import',
                'totalJobs' => 10,
                'pendingJobs' => 0,
                'failedJobs' => 0,
                'progress' => 100,
            ], 'Import (Finished, 100%)'],
            'bc_delivery' => [EntryType::BlazeCastDelivery->value, [
                'direction' => 'out',
                'event' => 'order.placed',
                'channel' => 'orders',
                'delivered_to' => 3,
            ], 'Outgoing order.placed on orders'],
            'bc_message' => [EntryType::BlazeCastMessage->value, [
                'event' => 'ping',
                'channel' => 'presence',
                'connection_id' => 'conn-1',
            ], 'Incoming ping on presence'],
            'broadcast' => [EntryType::Broadcast->value, [
                'event' => 'OrderPlaced',
                'channels' => ['orders', 'admin'],
                'connection' => 'default',
            ], 'OrderPlaced → orders, admin'],
            'explorator' => [EntryType::Explorator->value, [
                'operation' => 'search',
                'query' => 'red shoes',
                'engine' => 'meilisearch',
                'hits' => 7,
            ], 'search: red shoes'],
            'model' => [EntryType::Model->value, [
                'action' => 'created',
                'model' => 'App\Model\Entity\Article',
            ], 'Created App\Model\Entity\Article'],
            'mongo' => [EntryType::Mongo->value, [
                'command' => 'find',
                'summary' => 'find shop orders',
                'database' => 'shop',
                'time' => '12.34',
            ], 'find shop orders'],
            'mongo_query' => [EntryType::MongoQuery->value, [
                'query' => '{"find": "orders"}',
                'connection' => 'default',
                'time' => '3.21',
            ], '{"find": "orders"}'],
            'notification' => [EntryType::Notification->value, [
                'notification' => 'App\Notification\OrderShipped',
                'channel' => 'mail',
                'notifiable' => 'App\Model\Entity\User:7',
                'queued' => true,
            ], 'App\Notification\OrderShipped → App\Model\Entity\User:7'],
            'schedule' => [EntryType::ScheduledTask->value, [
                'description' => 'Nightly import',
                'command' => 'import:nightly',
                'expression' => '0 2 * * *',
            ], 'Nightly import'],
            'vardump' => [EntryType::VarDump->value, [
                'summary' => 'App\Model\Entity\User',
                'file' => '/app/src/Controller/OrdersController.php',
                'line' => 42,
                'vardumps' => ['<div>one</div>', '<div>two</div>'],
            ], 'App\Model\Entity\User'],
            'view' => [EntryType::View->value, [
                'name' => 'Orders/index',
                'path' => '/app/templates/Orders/index.php',
            ], 'Orders/index'],
        ];
    }

    /**
     * @return void
     */
    #[DataProvider('detailProvider')]
    public function testDetailFieldsShape(string $type, array $content, string $label, string $field): void
    {
        $presentation = PresentationRegistry::for($type);
        $detail = $presentation->detailFields($this->makeResult($type, $content));

        $this->assertSame($label, $detail['label']);
        $this->assertArrayHasKey($field, $detail['fields']);
        $this->assertIsArray($detail['blocks']);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function detailProvider(): array
    {
        return [
            'ai' => [EntryType::Ai->value, ['category' => 'tool', 'name' => 'Ai.x'], 'AI', 'Event'],
            'authorization' => [EntryType::Authorization->value, ['ability' => 'A/b'], 'Authorization', 'Ability'],
            'batch' => [EntryType::Batch->value, ['name' => 'B'], 'Batch', 'Progress'],
            'bc_delivery' => [EntryType::BlazeCastDelivery->value, ['event' => 'e'], 'BlazeCast', 'Direction'],
            'broadcast' => [EntryType::Broadcast->value, ['event' => 'E'], 'Broadcast', 'Channels'],
            'explorator' => [EntryType::Explorator->value, ['operation' => 'search'], 'Search', 'Operation'],
            'model' => [EntryType::Model->value, ['action' => 'updated'], 'Model', 'Action'],
            'mongo' => [EntryType::Mongo->value, ['command' => 'find'], 'Mongo', 'Command'],
            'mongo_query' => [EntryType::MongoQuery->value, ['query' => 'q'], 'Mongo Query', 'Location'],
            'mongo_query_log' => [EntryType::MongoQueryLog->value, ['query' => 'q'], 'Mongo Query Log', 'Location'],
            'notification' => [EntryType::Notification->value, ['channel' => 'mail'], 'Notification', 'Channel'],
            'schedule' => [EntryType::ScheduledTask->value, ['command' => 'c'], 'Schedule', 'Expression'],
            'vardump' => [EntryType::VarDump->value, ['summary' => 's'], 'Var Dump', 'Dumps'],
            'view' => [EntryType::View->value, ['name' => 'V/i'], 'View', 'View'],
        ];
    }

    /**
     * @return void
     */
    #[DataProvider('rowProvider')]
    public function testTableRowMatchesHeaders(string $type, array $content): void
    {
        $presentation = PresentationRegistry::for($type);
        $entry = $this->makeResult($type, $content);

        $this->assertCount(count($presentation->tableHeaders()), $presentation->tableRow($entry));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rowProvider(): array
    {
        $rows = [];
        foreach (self::summaryProvider() as $name => $row) {
            $rows[$name] = [$row[0], $row[1]];
        }

        return $rows;
    }

    /**
     * Build an entry result for presentation tests.
     *
     * @param string $type Entry type value.
     * @param array<string, mixed> $content Entry content.
     * @return \Crustum\Speculum\Entry\EntryResult
     */
    protected function makeResult(string $type, array $content): EntryResult
    {
        return new EntryResult(
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            1,
            'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            $type,
            null,
            $content,
            new DateTimeImmutable(),
        );
    }
}
