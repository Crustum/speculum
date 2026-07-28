<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Controller;

use Cake\Http\ServerRequest;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Crustum\Speculum\Test\TestCase\TestCaseBase;

/**
 * API list JSON shape coverage without full HTTP stack.
 */
class ApiEntriesShapeTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testListShapeMatchesContract(): void
    {
        $entry = IncomingEntry::make([
            'uri' => '/api-shape',
            'method' => 'GET',
            'response_status' => 200,
        ])->type(EntryType::Request->value)->batchId('33333333-3333-3333-3333-333333333333');
        $this->repository->store([$entry]);

        $request = new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'POST'],
            'post' => ['take' => 10],
        ]);
        $options = EntryQueryOptions::fromRequest($request);
        $entries = $this->repository->get(EntryType::Request->value, $options);
        $payload = [
            'entries' => array_map(static fn($item) => $item->jsonSerialize(), $entries),
            'status' => 'enabled',
        ];

        $this->assertArrayHasKey('entries', $payload);
        $this->assertArrayHasKey('status', $payload);
        $this->assertSame('enabled', $payload['status']);
        $this->assertNotEmpty($payload['entries']);
        $first = $payload['entries'][0];
        foreach (['id', 'sequence', 'batch_id', 'type', 'content', 'tags', 'family_hash', 'created', 'duration'] as $key) {
            $this->assertArrayHasKey($key, $first);
        }

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/',
            (string)$first['created'],
        );

        $this->assertSame(EntryType::Request->value, $first['type']);
    }

    /**
     * @return void
     */
    public function testMetaAvailableWatchers(): void
    {
        $watchers = WatcherRegistry::availableWatchers();
        $this->assertContains('requests', $watchers);
        $this->assertContains('mail', $watchers);
        $this->assertContains('models', $watchers);
        $this->assertContains('views', $watchers);
        $this->assertContains('vardumps', $watchers);
    }

    /**
     * @return void
     */
    public function testFromRequestClampsClientTake(): void
    {
        $default = EntryQueryOptions::fromRequest(new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'POST'],
            'post' => [],
        ]));
        $this->assertSame(EntryQueryOptions::DEFAULT_LIMIT, $default->limit);

        $neg = EntryQueryOptions::fromRequest(new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'POST'],
            'post' => ['take' => -1],
        ]));
        $this->assertSame(EntryQueryOptions::DEFAULT_LIMIT, $neg->limit);

        $huge = EntryQueryOptions::fromRequest(new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'POST'],
            'post' => ['take' => 999999],
        ]));
        $this->assertSame(EntryQueryOptions::MAX_CLIENT_LIMIT, $huge->limit);

        $ok = EntryQueryOptions::fromRequest(new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'POST'],
            'post' => ['take' => 25],
        ]));
        $this->assertSame(25, $ok->limit);

        $internal = (new EntryQueryOptions())->limit(EntryQueryOptions::UNLIMITED);
        $this->assertSame(EntryQueryOptions::UNLIMITED, $internal->limit);
    }
}
