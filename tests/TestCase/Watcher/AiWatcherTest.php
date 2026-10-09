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
use Stringable;

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
    public function testToolWrapperWithInnerMethodUnwrapsToRealTool(): void
    {
        $inner = new class {
        };
        $wrapper = new class ($inner) {
            public function __construct(private object $inner)
            {
            }

            public function inner(): object
            {
                return $this->inner;
            }

            public function name(): string
            {
                return 'ask_user';
            }
        };

        $watcher = new AiWatcher(['enabled' => true]);
        $watcher->record(new Event('Ai.invokingTool', null, [
            'invocationId' => 'inv-w',
            'toolInvocationId' => 'tool-1',
            'tool' => $wrapper,
            'arguments' => ['question' => 'Proceed?'],
        ]));

        $this->assertCount(1, Speculum::$entriesQueue);
        $content = Speculum::$entriesQueue[0]->content;

        $this->assertSame('ask_user', $content['tool_name']);
        $this->assertStringNotContainsString('EventedTool', $content['tool'] ?? '');
        $this->assertNotEmpty($content['tool_class']);
        $this->assertNotEmpty($content['tool_wrapper']);
        $this->assertSame('tool-1', $content['tool_invocation_id']);
        $this->assertStringContainsString('ask_user', $content['summary']);
    }

    /**
     * @return void
     */
    public function testToolWrapperWithPrivateInnerPropertyUnwraps(): void
    {
        $inner = new class {
        };
        $wrapper = new class ($inner)
        {
            /**
             * @var object
             */
            public object $inner;

            public function __construct(object $inner)
            {
                $this->inner = $inner;
            }
        };

        $watcher = new AiWatcher(['enabled' => true]);
        $watcher->record(new Event('Ai.toolInvoked', null, [
            'invocationId' => 'inv-p',
            'toolInvocationId' => 'tool-2',
            'tool' => $wrapper,
            'arguments' => [],
            'result' => 'ok',
            'time' => 5.0,
        ]));

        $this->assertCount(1, Speculum::$entriesQueue);
        $content = Speculum::$entriesQueue[0]->content;

        $this->assertNotEmpty($content['tool_class']);
        $this->assertNotEmpty($content['tool_wrapper']);
        $this->assertSame($content['tool_class'], $content['tool']);
    }

    /**
     * @return void
     */
    public function testPlainToolHasNoWrapperFields(): void
    {
        $watcher = new AiWatcher(['enabled' => true]);
        $watcher->record(new Event('Ai.invokingTool', null, [
            'invocationId' => 'inv-plain',
            'toolInvocationId' => 'tool-3',
            'tool' => new class {
            },
            'arguments' => [],
        ]));

        $this->assertCount(1, Speculum::$entriesQueue);
        $content = Speculum::$entriesQueue[0]->content;

        $this->assertNull($content['tool_wrapper']);
        $this->assertSame($content['tool_class'], $content['tool']);
    }

    /**
     * @return void
     */
    public function testExtractsThreadFromStepMessages(): void
    {
        $message = new class {
            public object $role;

            public ?string $content = 'compact';

            public function __construct()
            {
                $this->role = new class {
                    public string $value = 'user';
                };
            }
        };

        $watcher = new AiWatcher(['enabled' => true]);
        $watcher->record(new Event('Ai.startingStep', null, [
            'invocationId' => 'inv-t',
            'stepNumber' => 0,
            'messages' => [$message],
        ]));

        $this->assertCount(1, Speculum::$entriesQueue);
        $thread = Speculum::$entriesQueue[0]->content['thread'] ?? null;

        $this->assertIsArray($thread);
        $this->assertCount(1, $thread);
        $this->assertSame('user', $thread[0]['role']);
        $this->assertSame('compact', $thread[0]['content']);
    }

    /**
     * @return void
     */
    public function testExtractsResponseTextUsageAndToolCalls(): void
    {
        $response = new class {
            public string $text = 'It looks like you are trying…';

            public object $usage;

            /**
             * @var list<object>
             */
            public array $toolCalls = [];

            /**
             * @var list<object>
             */
            public array $messages = [];

            public function __construct()
            {
                $this->usage = new class {
                    /**
                     * @return array<string, int>
                     */
                    public function toArray(): array
                    {
                        return [
                            'prompt_tokens' => 100,
                            'completion_tokens' => 50,
                            'cache_write_input_tokens' => 0,
                            'cache_read_input_tokens' => 10,
                            'reasoning_tokens' => 0,
                        ];
                    }
                };
            }
        };

        $watcher = new AiWatcher(['enabled' => true]);
        $watcher->record(new Event('Ai.agentStreamed', null, [
            'invocationId' => 'inv-r',
            'response' => $response,
        ]));

        $this->assertCount(1, Speculum::$entriesQueue);
        $content = Speculum::$entriesQueue[0]->content;

        $this->assertSame('It looks like you are trying…', $content['response_text'] ?? null);
        $this->assertSame(
            ['prompt' => 100, 'completion' => 50, 'cache_read' => 10, 'cache_write' => 0, 'reasoning' => 0],
            $content['usage'] ?? null,
        );
    }

    /**
     * @return void
     */
    public function testExtractsResultTextFromStringableToolResult(): void
    {
        $result = new class implements Stringable {
            public function __toString(): string
            {
                return 'The user did not answer…';
            }
        };

        $watcher = new AiWatcher(['enabled' => true]);
        $watcher->record(new Event('Ai.toolInvoked', null, [
            'invocationId' => 'inv-rt',
            'tool' => new class {
            },
            'arguments' => [],
            'result' => $result,
            'time' => 10.0,
        ]));

        $this->assertCount(1, Speculum::$entriesQueue);

        $this->assertSame(
            'The user did not answer…',
            Speculum::$entriesQueue[0]->content['result_text'] ?? null,
        );
    }

    /**
     * @return void
     */
    public function testSkipsUnreadableShapesWithoutBreakingRecording(): void
    {
        $watcher = new AiWatcher(['enabled' => true]);
        $watcher->record(new Event('Ai.startingStep', null, [
            'invocationId' => 'inv-s',
            'stepNumber' => 1,
            'messages' => 'garbage',
            'response' => new class {
            },
        ]));

        $this->assertCount(1, Speculum::$entriesQueue);
        $content = Speculum::$entriesQueue[0]->content;

        $this->assertArrayNotHasKey('thread', $content);
        $this->assertArrayNotHasKey('response_text', $content);
        $this->assertArrayNotHasKey('usage', $content);
    }

    /**
     * @return void
     */
    public function testRecordsAgentClass(): void
    {
        $agent = new class {
        };

        $watcher = new AiWatcher(['enabled' => true]);
        $watcher->record(new Event('Ai.startingStep', null, [
            'invocationId' => 'inv-a',
            'stepNumber' => 0,
            'agent' => $agent,
            'messages' => [],
        ]));

        $this->assertCount(1, Speculum::$entriesQueue);
        $content = Speculum::$entriesQueue[0]->content;

        $this->assertSame($agent::class, $content['agent_class']);
    }

    /**
     * Approval events carry no `tool` object — the name, invocation id and
     * result text resolve from the first `toolResults` item.
     *
     * @return void
     */
    public function testApprovalEventResolvesToolFromToolResults(): void
    {
        $toolResult = new class {
            public string $id = 'z2qprqhzy';

            public string $name = 'apply-indexes';

            /**
             * @var array<string, mixed>
             */
            public array $arguments = ['indexes' => '[{"table": "posts"}]'];

            public string $result = 'Applied 1 index(es)';

            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return [
                    'id' => $this->id,
                    'name' => $this->name,
                    'arguments' => $this->arguments,
                    'result' => $this->result,
                    'result_id' => $this->id,
                ];
            }
        };

        $watcher = new AiWatcher(['enabled' => true]);
        $watcher->record(new Event('Ai.toolApprovalResolved', null, [
            'invocationId' => 'inv-appr',
            'toolResults' => [$toolResult],
        ]));

        $this->assertCount(1, Speculum::$entriesQueue);
        $content = Speculum::$entriesQueue[0]->content;

        $this->assertSame('tool', $content['category']);
        $this->assertSame('apply-indexes', $content['tool_name']);
        $this->assertSame('z2qprqhzy', $content['tool_invocation_id']);
        $this->assertSame('apply-indexes · toolApprovalResolved', $content['summary']);
        $this->assertSame('apply-indexes', $content['response_tool_results'][0]['name'] ?? null);
        $this->assertSame('Applied 1 index(es)', $content['result_text']);
    }

    /**
     * Embeddings prompts expose texts as `inputs`; embeddings responses carry
     * float vectors, so only the shape (count × dimensions) is extracted.
     *
     * @return void
     */
    public function testEmbeddingsPromptAndResponseAreReadable(): void
    {
        $prompt = new class {
            /**
             * @var list<string>
             */
            public array $inputs = ['i want add table field into page'];

            public int $dimensions = 2048;
        };
        $response = new class {
            /**
             * @var list<list<float>>
             */
            public array $embeddings = [[0.10947755, 0.034772266]];
        };

        $watcher = new AiWatcher(['enabled' => true]);
        $watcher->record(new Event('Ai.generatingEmbeddings', null, [
            'invocationId' => 'inv-emb',
            'model' => 'nvidia/nemotron-3-embed-1b:free',
            'prompt' => $prompt,
        ]));
        $watcher->record(new Event('Ai.embeddingsGenerated', null, [
            'invocationId' => 'inv-emb',
            'model' => 'nvidia/nemotron-3-embed-1b:free',
            'response' => $response,
        ]));

        $this->assertCount(2, Speculum::$entriesQueue);
        $start = Speculum::$entriesQueue[0]->content;
        $finish = Speculum::$entriesQueue[1]->content;

        $this->assertSame('generation', $start['category']);
        $this->assertSame('i want add table field into page', $start['prompt_text']);
        $this->assertSame('prompt', $start['thread'][0]['role'] ?? null);

        $this->assertSame(['count' => 1, 'dimensions' => 2], $finish['response_embeddings']);
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
