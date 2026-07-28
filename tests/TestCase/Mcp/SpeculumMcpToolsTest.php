<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Mcp;

use Cake\Utility\Text;
use Crustum\Mcp\Request;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Mcp\Tools\Control;
use Crustum\Speculum\Mcp\Tools\Entry;
use Crustum\Speculum\Mcp\Tools\Search;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Crustum\Speculum\Test\TestCase\TestCaseBase;

/**
 * Speculum MCP tool coverage.
 */
class SpeculumMcpToolsTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testSearchReturnsSummariesAndMeta(): void
    {
        $batchId = Text::uuid();
        $entry = IncomingEntry::make([
            'uri' => '/posts',
            'method' => 'GET',
            'response_status' => 200,
        ])->type(EntryType::Request->value)->batchId($batchId);
        $this->repository->store([$entry]);

        $tool = new Search();
        $response = $tool->handle(new Request([
            'type' => EntryType::Request->value,
            'limit' => 10,
        ]));

        $this->assertFalse($response->isError());
        $payload = json_decode((string)$response->content(), true);
        $this->assertIsArray($payload);
        $this->assertSame(1, $payload['count']);
        $this->assertSame($entry->uuid, $payload['entries'][0]['id']);
        $this->assertSame('GET /posts', $payload['entries'][0]['label']);
        $this->assertArrayHasKey('recording', $payload['meta']);
        $this->assertArrayHasKey('available_watchers', $payload['meta']);
    }

    /**
     * @return void
     */
    public function testSearchFiltersSinceSequence(): void
    {
        $batchId = Text::uuid();
        $first = IncomingEntry::make(['message' => 'one'])->type(EntryType::Log->value)->batchId($batchId);
        $second = IncomingEntry::make(['message' => 'two'])->type(EntryType::Log->value)->batchId($batchId);
        $this->repository->store([$first, $second]);

        $listed = $this->repository->get(EntryType::Log->value, (new EntryQueryOptions())->limit(10));
        $this->assertCount(2, $listed);
        $olderSequence = min((int)$listed[0]->sequence, (int)$listed[1]->sequence);

        $tool = new Search();
        $response = $tool->handle(new Request([
            'type' => EntryType::Log->value,
            'since_sequence' => $olderSequence,
            'limit' => 10,
        ]));

        $payload = json_decode((string)$response->content(), true);
        $this->assertSame(1, $payload['count']);
        $this->assertGreaterThan($olderSequence, (int)$payload['entries'][0]['sequence']);
    }

    /**
     * @return void
     */
    public function testEntryReturnsDetailAndRelatedExpand(): void
    {
        $batchId = Text::uuid();
        $request = IncomingEntry::make([
            'uri' => '/fail',
            'method' => 'POST',
        ])->type(EntryType::Request->value)->batchId($batchId);
        $exception = IncomingEntry::make([
            'class' => 'RuntimeException',
            'message' => 'boom',
        ])->type(EntryType::Exception->value)->batchId($batchId);
        $this->repository->store([$request, $exception]);

        $tool = new Entry();
        $response = $tool->handle(new Request([
            'id' => $exception->uuid,
            'expand' => 'related',
        ]));

        $this->assertFalse($response->isError());
        $payload = json_decode((string)$response->content(), true);
        $this->assertSame($exception->uuid, $payload['entry']['id']);
        $this->assertSame(EntryType::Exception->value, $payload['entry']['type']);
        $this->assertArrayHasKey('related', $payload);
        $this->assertGreaterThanOrEqual(2, $payload['related']['counts'][EntryType::Request->value]
            + $payload['related']['counts'][EntryType::Exception->value]);
    }

    /**
     * @return void
     */
    public function testEntryAcceptsIdFromSearchSummary(): void
    {
        $batchId = Text::uuid();
        $entry = IncomingEntry::make([
            'message' => 'via-id',
        ])->type(EntryType::Log->value)->batchId($batchId);
        $this->repository->store([$entry]);

        $tool = new Entry();
        $response = $tool->handle(new Request([
            'id' => $entry->uuid,
        ]));

        $this->assertFalse($response->isError());
        $payload = json_decode((string)$response->content(), true);
        $this->assertSame($entry->uuid, $payload['entry']['id']);
    }

    /**
     * @return void
     */
    public function testEntryMissingIdErrors(): void
    {
        $tool = new Entry();
        $response = $tool->handle(new Request([]));

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('id', (string)$response->content());
    }

    /**
     * @return void
     */
    public function testControlStatusDefault(): void
    {
        $tool = new Control();
        $response = $tool->handle(new Request([]));

        $this->assertFalse($response->isError());
        $payload = json_decode((string)$response->content(), true);
        $this->assertSame('status', $payload['action']);
        $this->assertArrayHasKey('enabled', $payload['status']);
        $this->assertArrayHasKey('paused', $payload['status']);
        $this->assertArrayHasKey('recording', $payload['status']);
        $this->assertArrayHasKey('monitored_tags', $payload['status']);
        $this->assertIsArray($payload['status']['monitored_tags']);
        $this->assertTrue($payload['status']['recording']);
        $this->assertFalse($payload['status']['paused']);
    }

    /**
     * @return void
     */
    public function testControlPauseAndResume(): void
    {
        $tool = new Control();

        $paused = $tool->handle(new Request(['action' => 'pause']));
        $this->assertFalse($paused->isError());
        $pausePayload = json_decode((string)$paused->content(), true);
        $this->assertSame('pause', $pausePayload['action']);
        $this->assertTrue($pausePayload['status']['paused']);
        $this->assertFalse($pausePayload['status']['recording']);
        $this->assertFalse(Speculum::isRecording());

        $resumed = $tool->handle(new Request(['action' => 'resume']));
        $this->assertFalse($resumed->isError());
        $resumePayload = json_decode((string)$resumed->content(), true);
        $this->assertSame('resume', $resumePayload['action']);
        $this->assertFalse($resumePayload['status']['paused']);
        $this->assertTrue($resumePayload['status']['recording']);
        $this->assertTrue(Speculum::isRecording());
    }

    /**
     * @return void
     */
    public function testControlMonitorAndUnmonitorTag(): void
    {
        $tool = new Control();

        $missing = $tool->handle(new Request(['action' => 'monitor']));
        $this->assertTrue($missing->isError());

        $monitored = $tool->handle(new Request([
            'action' => 'monitor',
            'tag' => 'Auth',
        ]));
        $this->assertFalse($monitored->isError());
        $monitorPayload = json_decode((string)$monitored->content(), true);
        $this->assertSame('monitor', $monitorPayload['action']);
        $this->assertSame('Auth', $monitorPayload['tag']);
        $this->assertContains('Auth', $monitorPayload['status']['monitored_tags']);
        $this->assertContains('Auth', Speculum::getRepository()->monitoring());

        $removed = $tool->handle(new Request([
            'action' => 'unmonitor',
            'tag' => 'Auth',
        ]));
        $this->assertFalse($removed->isError());
        $removePayload = json_decode((string)$removed->content(), true);
        $this->assertSame('unmonitor', $removePayload['action']);
        $this->assertNotContains('Auth', $removePayload['status']['monitored_tags']);
        $this->assertNotContains('Auth', Speculum::getRepository()->monitoring());
    }
}
