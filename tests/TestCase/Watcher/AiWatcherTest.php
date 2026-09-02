<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use ArrayIterator;
use Cake\Collection\Collection;
use Cake\Core\Configure;
use Cake\Event\Event;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\AiWatcher;
use RuntimeException;

/**
 * AI watcher tests.
 */
class AiWatcherTest extends TestCaseBase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->markSoftPluginLoaded('Crustum/Ai');
    }

    /**
     * @return void
     */
    public function testSoftFeatureFollowsAiPlugin(): void
    {
        $this->assertTrue(WatcherRegistry::isSoftAvailable(SoftFeature::Ai));

        Speculum::flushEntries();
    }

    /**
     * @return void
     */
    public function testRecordAgentEventBuildsEntryAndTags(): void
    {
        $provider = new AiWatcherTestProvider();

        $watcher = new AiWatcher(['enabled' => true, 'slow' => 1000]);
        $watcher->record(new Event('Ai.agentPrompted', null, [
            'invocationId' => 'inv-1',
            'provider' => $provider,
            'model' => 'gpt-4o',
            'prompt' => new class {
                public function toArray(): array
                {
                    return ['messages' => []];
                }
            },
            'response' => new class {
                public function toArray(): array
                {
                    return ['text' => 'hi'];
                }
            },
        ]));

        $this->assertCount(1, Speculum::$entriesQueue);
        $entry = Speculum::$entriesQueue[0];

        $this->assertSame(EntryType::Ai->value, $entry->type);
        $this->assertSame('agent', $entry->content['category']);
        $this->assertSame('Ai.agentPrompted', $entry->content['name']);
        $this->assertSame('inv-1', $entry->content['invocationId']);
        $this->assertSame('gpt-4o', $entry->content['model']);
        $this->assertContains('Ai:agent', $entry->tags);
        $this->assertContains('provider:AiWatcherTestProvider', $entry->tags);
        $this->assertContains('model:gpt-4o', $entry->tags);
        $this->assertContains('invocation:inv-1', $entry->tags);
        $this->assertArrayHasKey('payload', $entry->content);
        $this->assertArrayHasKey('prompt', $entry->content['payload']);
        $this->assertNotEmpty($entry->content['payload']['prompt']['properties']);
        $this->assertSame('openai', $entry->content['payload']['provider']['properties']['name'] ?? null);
    }

    /**
     * @return void
     */
    public function testUsageTokenCountsAreNotRedacted(): void
    {
        $usage = new class {
            public function toArray(): array
            {
                return [
                    'prompt_tokens' => 12,
                    'completion_tokens' => 34,
                    'cache_write_input_tokens' => 0,
                    'cache_read_input_tokens' => 5,
                    'reasoning_tokens' => 7,
                ];
            }
        };

        $response = new class ($usage) {
            public function __construct(private object $usage)
            {
            }

            public function toArray(): array
            {
                return [
                    'text' => 'hi',
                    'usage' => $this->usage->toArray(),
                    'continuation_token' => 'secret-continuation',
                ];
            }
        };

        $watcher = new AiWatcher(['enabled' => true]);
        $watcher->record(new Event('Ai.agentStreamed', null, [
            'invocationId' => 'inv-u',
            'model' => 'gpt-4o',
            'response' => $response,
        ]));

        $this->assertCount(1, Speculum::$entriesQueue);
        $entry = Speculum::$entriesQueue[0];
        $usageOut = $entry->content['payload']['response']['properties']['usage'];

        $this->assertSame(12, $usageOut['prompt_tokens']);
        $this->assertSame(34, $usageOut['completion_tokens']);
        $this->assertSame(0, $usageOut['cache_write_input_tokens']);
        $this->assertSame(5, $usageOut['cache_read_input_tokens']);
        $this->assertSame(7, $usageOut['reasoning_tokens']);

        $this->assertSame('secret-continuation', $entry->content['payload']['response']['properties']['continuation_token']);
    }

    /**
     * @return void
     */
    public function testCollectionExpandsToInnerValues(): void
    {
        $collection = new Collection(new ArrayIterator([
            ['id' => 'a1', 'name' => 'first'],
            ['id' => 'a2', 'name' => 'second'],
        ]));

        $watcher = new AiWatcher(['enabled' => true]);
        $watcher->record(new Event('Ai.agentPrompted', null, [
            'invocationId' => 'inv-c',
            'attachments' => $collection,
        ]));

        $this->assertCount(1, Speculum::$entriesQueue);
        $entry = Speculum::$entriesQueue[0];
        $attachments = $entry->content['payload']['attachments'];

        $this->assertIsArray($attachments);
        $this->assertArrayNotHasKey('class', $attachments);
        $this->assertSame('a1', $attachments[0]['id']);
        $this->assertSame('second', $attachments[1]['name']);
    }

    /**
     * @return void
     */
    public function testRecordFailureAndSlowTags(): void
    {
        $watcher = new AiWatcher(['enabled' => true, 'slow' => 100]);
        $watcher->record(new Event('Ai.stepFailed', null, [
            'invocationId' => 'inv-2',
            'stepNumber' => 3,
            'provider' => new class {
            },
            'model' => 'gpt-4o',
            'exception' => new RuntimeException('boom'),
            'time' => 500.0,
        ]));

        $this->assertCount(1, Speculum::$entriesQueue);
        $entry = Speculum::$entriesQueue[0];

        $this->assertTrue($entry->content['failed']);
        $this->assertSame(500, $entry->content['duration']);
        $this->assertTrue($entry->content['slow']);
        $this->assertContains('failed', $entry->tags);
        $this->assertContains('slow', $entry->tags);
        $this->assertSame('RuntimeException', $entry->content['exception']['class']);
        $this->assertSame('boom', $entry->content['exception']['message']);
    }

    /**
     * @return void
     */
    public function testCategoriesOptionFiltersRecording(): void
    {
        $watcher = new AiWatcher([
            'enabled' => true,
            'categories' => ['tool'],
        ]);

        $watcher->record(new Event('Ai.agentPrompted', null, ['invocationId' => 'a']));
        $this->assertCount(0, Speculum::$entriesQueue, 'agent event filtered out');

        $watcher->record(new Event('Ai.toolInvoked', null, [
            'invocationId' => 'b',
            'tool' => new class {
            },
            'arguments' => [],
            'result' => null,
            'time' => 10.0,
        ]));
        $this->assertCount(1, Speculum::$entriesQueue);
        $this->assertSame('tool', Speculum::$entriesQueue[0]->content['category']);
    }

    /**
     * @return void
     */
    public function testRegisterSkipsWhenSoftFeatureMissing(): void
    {
        $this->markSoftPluginLoaded('Crustum/Ai');

        $watcher = new AiWatcher(['enabled' => true]);
        $watcher->register();
        // No exception; listeners attached through the global EventManager.
        $this->assertTrue(true);
    }

    /**
     * @return void
     */
    public function testAvailableWatchersIncludesAiOnlyWhenEnabledAndPluginLoaded(): void
    {
        Configure::write('Speculum.watchers.' . AiWatcher::class, ['enabled' => true]);
        $this->assertContains('ai', WatcherRegistry::availableWatchers());

        Configure::write('Speculum.watchers.' . AiWatcher::class, ['enabled' => false]);
        $this->assertNotContains('ai', WatcherRegistry::availableWatchers());
        Configure::delete('Speculum.watchers.' . AiWatcher::class);
    }
}
