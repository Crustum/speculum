<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Collection\CollectionInterface;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Sanitizer\SensitiveData;
use Crustum\Speculum\Speculum;
use ReflectionClass;
use SplObjectStorage;
use Stringable;
use Throwable;
use Traversable;

/**
 * Soft AI watcher.
 *
 * Records the Crustum/Ai plugin's `Ai.*` events as Speculum `ai` entries.
 * Subscribes to the known exact event names (CakePHP supports only exact
 * `on()` listeners, no wildcards). Active only when SoftFeature::Ai is available
 * (the `crustum/cakephp-ai` plugin is loaded); never requires it at parse time —
 * the file references no `Crustum\Ai\*` class, and `record()` works purely on the
 * event name and its payload array.
 */
class AiWatcher extends Watcher
{
    /**
     * Exact AI event names this watcher listens to (AiEvent::eventName() values).
     *
     * @var list<string>
     */
    private const EVENTS = [
        'Ai.addingFileToStore',
        'Ai.agentFailed',
        'Ai.agentFailedOver',
        'Ai.agentPrompted',
        'Ai.agentStreamed',
        'Ai.audioGenerated',
        'Ai.creatingStore',
        'Ai.embeddingsGenerated',
        'Ai.fileAddedToStore',
        'Ai.fileDeleted',
        'Ai.fileRemovedFromStore',
        'Ai.fileStored',
        'Ai.generatingAudio',
        'Ai.generatingEmbeddings',
        'Ai.generatingImage',
        'Ai.generatingTranscription',
        'Ai.imageGenerated',
        'Ai.invokingTool',
        'Ai.promptingAgent',
        'Ai.providerFailedOver',
        'Ai.removingFileFromStore',
        'Ai.reranked',
        'Ai.reranking',
        'Ai.startingStep',
        'Ai.stepCompleted',
        'Ai.stepFailed',
        'Ai.storeCreated',
        'Ai.storeDeleted',
        'Ai.storingFile',
        'Ai.streamingAgent',
        'Ai.toolApprovalRequested',
        'Ai.toolApprovalResolved',
        'Ai.toolFailed',
        'Ai.toolInvoked',
        'Ai.transcriptionGenerated',
    ];

    /**
     * Short event name (after `Ai.`) → category discriminator.
     *
     * @var array<string, string>
     */
    private const CATEGORY = [
        'promptingAgent' => 'agent',
        'streamingAgent' => 'agent',
        'agentPrompted' => 'agent',
        'agentStreamed' => 'agent',
        'agentFailed' => 'agent',
        'startingStep' => 'agent',
        'stepCompleted' => 'agent',
        'stepFailed' => 'agent',
        'invokingTool' => 'tool',
        'toolInvoked' => 'tool',
        'toolFailed' => 'tool',
        'toolApprovalRequested' => 'tool',
        'toolApprovalResolved' => 'tool',
        'generatingImage' => 'generation',
        'imageGenerated' => 'generation',
        'generatingAudio' => 'generation',
        'audioGenerated' => 'generation',
        'generatingTranscription' => 'generation',
        'transcriptionGenerated' => 'generation',
        'generatingEmbeddings' => 'generation',
        'embeddingsGenerated' => 'generation',
        'reranking' => 'generation',
        'reranked' => 'generation',
        'creatingStore' => 'store',
        'storeCreated' => 'store',
        'storeDeleted' => 'store',
        'addingFileToStore' => 'store',
        'fileAddedToStore' => 'store',
        'removingFileFromStore' => 'store',
        'fileRemovedFromStore' => 'store',
        'storingFile' => 'file',
        'fileStored' => 'file',
        'fileDeleted' => 'file',
        'agentFailedOver' => 'failover',
        'providerFailedOver' => 'failover',
    ];

    /**
     * Short event names that represent a failure.
     *
     * @var list<string>
     */
    private const FAILED = [
        'agentFailed',
        'stepFailed',
        'toolFailed',
        'agentFailedOver',
        'providerFailedOver',
    ];

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        if (!WatcherRegistry::isSoftAvailable(SoftFeature::Ai)) {
            return;
        }

        $ignore = $this->options['ignore'] ?? [];

        foreach (self::EVENTS as $name) {
            if (in_array($name, $ignore, true)) {
                continue;
            }

            EventManager::instance()->on($name, function (EventInterface $event): void {
                $this->record($event);
            });
        }
    }

    /**
     * Record an AI event as a Speculum entry.
     *
     * @param \Cake\Event\EventInterface<object> $event Dispatched AI event.
     * @return void
     */
    public function record(EventInterface $event): void
    {
        if (!Speculum::isRecording()) {
            return;
        }

        $name = $event->getName();
        $short = substr($name, strlen('Ai.'));
        $category = self::CATEGORY[$short] ?? 'agent';

        $categories = $this->options['categories'] ?? null;
        if (is_array($categories) && !in_array($category, $categories, true)) {
            return;
        }

        $data = $event->getData();

        $toolObject = $data['tool'] ?? null;
        $toolClass = is_object($toolObject) ? $this->toolClass($toolObject) : null;
        $toolName = is_object($toolObject) ? $this->toolDisplayName($toolObject) : null;
        $toolWrapper = null;
        if (is_object($toolObject) && $toolClass !== null && $toolClass !== $this->shortClass($toolObject)) {
            $toolWrapper = $this->shortClass($toolObject);
        }

        // Approval events (Ai.toolApprovalRequested/Resolved) carry no `tool`
        // object — the approved tool arrives as `toolResults` items instead.
        // Resolve the display name and invocation id from the first result so
        // the summary and history call line show e.g. `apply-indexes(…)`
        // instead of a bare `tool()`.
        $firstToolResultRow = null;
        if (isset($data['toolResults'])) {
            foreach ($this->toPlainList($data['toolResults']) as $candidate) {
                $row = $this->readToolResult($candidate);
                if ($row !== null) {
                    $firstToolResultRow = $row;
                    break;
                }
            }
        }

        $toolName ??= $firstToolResultRow['name'] ?? null;

        $toolInvocationId = $data['toolInvocationId'] ?? $firstToolResultRow['id'] ?? null;

        $agentObject = $data['agent'] ?? null;

        $content = [
            'category' => $category,
            'name' => $name,
            'invocationId' => $data['invocationId'] ?? null,
            'provider' => isset($data['provider']) ? $this->shortClass($data['provider']) : null,
            'model' => $data['model'] ?? null,
            'tool' => $toolClass ?? (is_scalar($toolObject) ? (string)$toolObject : null),
            'tool_name' => $toolName,
            'tool_class' => $toolClass,
            'tool_wrapper' => $toolWrapper,
            'tool_invocation_id' => $toolInvocationId,
            'agent_class' => is_object($agentObject) ? $agentObject::class : null,
            'step' => $data['stepNumber'] ?? null,
            'is_final' => $data['isFinalStep'] ?? null,
            'failed' => in_array($short, self::FAILED, true),
            'exception' => isset($data['exception']) && $data['exception'] instanceof Throwable
                ? ['class' => $data['exception']::class, 'message' => $data['exception']->getMessage()]
                : null,
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
            'store_id' => $data['storeId'] ?? null,
            'store_name' => $data['name'] ?? null,
            'file_id' => $data['fileId'] ?? null,
            'document_id' => $data['documentId'] ?? null,
            'summary' => $this->summary($category, $short, $data, $toolName ?? $toolClass),
        ];

        foreach ($this->extractReadable($data) as $key => $value) {
            $content[$key] = $value;
        }

        $tags = ['Ai:' . $category];
        if ($content['provider'] !== null) {
            $tags[] = 'provider:' . $content['provider'];
        }

        if ($content['model'] !== null) {
            $tags[] = 'model:' . $content['model'];
        }

        if ($content['invocationId'] !== null) {
            $tags[] = 'invocation:' . $content['invocationId'];
        }

        if ($content['failed']) {
            $tags[] = 'failed';
        }

        $duration = isset($data['time']) ? (int)round((float)$data['time']) : null;
        [$content, $slowTags] = $this->withMeasuredDuration($content, $duration);
        $tags = array_merge($tags, $slowTags);

        $content['payload'] = $this->extractPayload($data);

        $this->normalizePayload($content);

        Speculum::recordEntry(
            EntryType::Ai,
            IncomingEntry::make($content)->tags(array_values(array_unique($tags))),
        );
    }

    /**
     * Build a short human-readable summary of the event.
     *
     * @param string $category Event category.
     * @param string $short Short event name (after `Ai.`).
     * @param array<string, mixed> $data Event payload.
     * @param string|null $toolLabel Resolved tool label (display name or inner class).
     * @return string
     */
    protected function summary(string $category, string $short, array $data, ?string $toolLabel = null): string
    {
        return match ($category) {
            'generation' => ($data['model'] ?? 'generation') . ' · ' . $short,
            'tool' => ($toolLabel ?? 'tool') . ' · ' . $short,
            'failover' => ($data['from'] ?? '?') . ' → ' . ($data['to'] ?? '?'),
            'store' => ($data['name'] ?? $data['storeId'] ?? 'store'),
            'file' => ($data['fileId'] ?? $data['storeId'] ?? 'file'),
            default => $short . (isset($data['stepNumber']) ? ' · step ' . $data['stepNumber'] : ''),
        };
    }

    /**
     * Resolve the short class name of the tool behind a (possibly wrapped) tool.
     *
     * Decorators expose the wrapped tool differently: Panifex/AiCoder `EventedTool`
     * via an `inner()` method, others via `getInner()` or an `inner` property
     * (often private, so it is read through reflection instead of direct access,
     * which cannot reach private properties from this scope). Surface the real
     * tool for display while the raw wrapper is still preserved in `payload`.
     *
     * @param object $tool Tool instance (possibly a wrapper).
     * @return string
     */
    protected function toolClass(object $tool): string
    {
        $inner = $this->unwrapTool($tool);

        return $inner !== null ? $this->shortClass($inner) : $this->shortClass($tool);
    }

    /**
     * Resolve the human-readable tool name when the tool provides one.
     *
     * Panifex/AiCoder `EventedTool::name()` forwards the inner tool's resolved
     * name via `ToolNameResolver`, which is more useful than any class name.
     *
     * @param object $tool Tool instance (possibly a wrapper).
     * @return string|null
     */
    protected function toolDisplayName(object $tool): ?string
    {
        try {
            if (method_exists($tool, 'name')) {
                $name = $tool->name();

                if ($name instanceof Stringable || is_string($name)) {
                    $name = (string)$name;

                    return $name !== '' ? $name : null;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Unwrap a decorated tool to the innermost tool instance.
     *
     * @param object $tool Tool instance (possibly a wrapper).
     * @return object|null
     */
    protected function unwrapTool(object $tool): ?object
    {
        try {
            if (method_exists($tool, 'inner')) {
                $candidate = $tool->inner();

                return is_object($candidate) && $candidate !== $tool ? $candidate : null;
            }

            if (method_exists($tool, 'getInner')) {
                $candidate = $tool->getInner();

                return is_object($candidate) && $candidate !== $tool ? $candidate : null;
            }

            if (property_exists($tool, 'inner')) {
                $reflection = new ReflectionClass($tool);

                if ($reflection->hasProperty('inner')) {
                    $property = $reflection->getProperty('inner');

                    if ($property->isInitialized($tool)) {
                        $candidate = $property->getValue($tool);

                        return is_object($candidate) && $candidate !== $tool ? $candidate : null;
                    }
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Extract UI-ready data from live event objects.
     *
     * The raw `payload` keeps the fully serialized objects for debugging, but
     * its nesting is unpredictable (agent buried under `prompt.properties`,
     * messages as `{ class, properties }` trees). Working with the live objects
     * here is far easier, so the stable, UI-facing fields are extracted now:
     * `thread` (role/content messages), `prompt_text`, `response_text`,
     * `response_tool_calls`, `response_tool_results`, `response_steps`, `usage`,
     * `response_embeddings` (count × dimensions, no float vectors)
     * and `result_text` for tool finishes.
     *
     * Everything is duck-typed (public properties and `toArray()` only, no
     * `Crustum\Ai\*` references) so the watcher stays soft-gated, and any
     * unexpected shape is skipped instead of breaking recording.
     *
     * @param array<string, mixed> $data Event payload.
     * @return array<string, mixed>
     */
    protected function extractReadable(array $data): array
    {
        try {
            $out = [];

            $thread = $this->readThread($data);
            if ($thread !== []) {
                $out['thread'] = $thread;
            }

            $promptText = $this->readPromptText($data['prompt'] ?? null);
            if ($promptText !== null) {
                $out['prompt_text'] = $promptText;
            }

            $response = $data['response'] ?? null;
            if (is_object($response)) {
                $responseText = $this->readText($response);
                if ($responseText !== null) {
                    $out['response_text'] = $responseText;
                }

                $toolCalls = $this->readToolCalls($response);
                if ($toolCalls !== []) {
                    $out['response_tool_calls'] = $toolCalls;
                }

                $toolResults = $this->readToolResults($response);
                if ($toolResults !== []) {
                    $out['response_tool_results'] = $toolResults;
                }

                $steps = $this->readSteps($response);
                if ($steps !== []) {
                    $out['response_steps'] = $steps;
                }

                $usage = $this->readUsage($response);
                if ($usage !== null) {
                    $out['usage'] = $usage;
                }

                // Embeddings responses carry float vectors instead of text —
                // store only the shape (count × dimensions), the raw floats
                // stay in the serialized payload for debugging.
                $embeddings = $this->readEmbeddings($response);
                if ($embeddings !== null) {
                    $out['response_embeddings'] = $embeddings;
                }
            }

            if (array_key_exists('result', $data)) {
                $resultText = $this->stringifyResult($data['result'], 20000);
                if ($resultText !== null) {
                    $out['result_text'] = $resultText;
                }
            }

            // Approval events expose tool results at the top level instead of
            // under `response` — surface them the same way so history shows
            // the call line and result without touching the raw payload.
            if (!isset($out['response_tool_results']) && isset($data['toolResults'])) {
                $rows = [];
                foreach ($this->toPlainList($data['toolResults']) as $toolResult) {
                    $row = $this->readToolResult($toolResult);
                    if ($row !== null) {
                        $rows[] = $row;
                    }
                }

                if ($rows !== []) {
                    $out['response_tool_results'] = array_slice($rows, 0, 50);

                    $firstText = $rows[0]['result_text'] ?? null;
                    if (is_string($firstText) && $firstText !== '' && !isset($out['result_text'])) {
                        $out['result_text'] = $this->capText($firstText, 20000);
                    }
                }
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Build the conversation thread from step messages, prompt text and response messages.
     *
     * @param array<string, mixed> $data Event payload.
     * @return list<array{role: string, content: string|null, tool_calls: list<array<string, mixed>>, tool_results: list<array<string, mixed>>}>
     */
    protected function readThread(array $data): array
    {
        $thread = [];

        foreach ($this->toPlainList($data['messages'] ?? null) as $message) {
            $row = $this->readMessage($message);
            if ($row !== null) {
                $thread[] = $row;
            }
        }

        if (array_key_exists('prompt', $data) && is_object($data['prompt'])) {
            $promptText = $this->readPromptText($data['prompt']);
            if ($promptText !== null) {
                $thread[] = ['role' => 'prompt', 'content' => $promptText, 'tool_calls' => [], 'tool_results' => []];
            }
        }

        $response = $data['response'] ?? null;
        if (is_object($response)) {
            foreach ($this->readMessagesProp($response) as $row) {
                $thread[] = $row;
            }
        }

        return array_slice($thread, 0, 100);
    }

    /**
     * Read the `messages` property of a response-like object.
     *
     * @param object $response Response object.
     * @return list<array{role: string, content: string|null, tool_calls: list<array<string, mixed>>, tool_results: list<array<string, mixed>>}>
     */
    protected function readMessagesProp(object $response): array
    {
        $out = [];

        try {
            if (!isset($response->messages)) {
                return [];
            }

            foreach ($this->toPlainList($response->messages) as $message) {
                $row = $this->readMessage($message);
                if ($row !== null) {
                    $out[] = $row;
                }
            }
        } catch (Throwable) {
            return [];
        }

        return $out;
    }

    /**
     * Normalize one message-like object to a role/content row.
     *
     * @param mixed $message Message object.
     * @return array{role: string, content: string|null, tool_calls: list<array<string, mixed>>, tool_results: list<array<string, mixed>>}|null
     */
    protected function readMessage(mixed $message): ?array
    {
        if (!is_object($message)) {
            return null;
        }

        try {
            $role = $this->readRole($message->role ?? null);
            $content = $this->readMessageContent($message);

            $toolCalls = [];
            if (isset($message->toolCalls)) {
                foreach ($this->toPlainList($message->toolCalls) as $toolCall) {
                    $row = $this->readToolCall($toolCall);
                    if ($row !== null) {
                        $toolCalls[] = $row;
                    }
                }
            }

            $toolResults = [];
            if (isset($message->toolResults)) {
                foreach ($this->toPlainList($message->toolResults) as $toolResult) {
                    $row = $this->readToolResult($toolResult);
                    if ($row !== null) {
                        $toolResults[] = $row;
                    }
                }
            }

            if ($role === null && $content === null && $toolCalls === [] && $toolResults === []) {
                return null;
            }

            return [
                'role' => $role ?? 'unknown',
                'content' => $content,
                'tool_calls' => $toolCalls,
                'tool_results' => $toolResults,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resolve a message role to its string value.
     *
     * Accepts backed enums (via `->value`), objects exposing `value`/`name`,
     * and plain strings.
     *
     * @param mixed $role Role value.
     * @return string|null
     */
    protected function readRole(mixed $role): ?string
    {
        if (is_string($role)) {
            return $role !== '' ? $role : null;
        }

        if (!is_object($role)) {
            return null;
        }

        try {
            if (isset($role->value) && is_string($role->value) && $role->value !== '') {
                return $role->value;
            }

            if (isset($role->name) && is_string($role->name) && $role->name !== '') {
                return $role->name;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Read string content from a message-like object.
     *
     * @param object $message Message object.
     * @return string|null
     */
    protected function readMessageContent(object $message): ?string
    {
        try {
            if (!isset($message->content)) {
                return null;
            }

            return $this->stringifyResult($message->content, 8000);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Read prompt text from a prompt-like object (`AgentPrompt` exposes
     * `prompt`; embeddings-style prompts expose the input texts as `inputs`).
     *
     * @param mixed $prompt Prompt object.
     * @return string|null
     */
    protected function readPromptText(mixed $prompt): ?string
    {
        if (!is_object($prompt)) {
            return null;
        }

        try {
            if (isset($prompt->prompt)) {
                return $this->stringifyResult($prompt->prompt, 8000);
            }

            if (isset($prompt->inputs)) {
                $texts = [];
                foreach ($this->toPlainList($prompt->inputs) as $input) {
                    $text = $this->stringifyResult($input, 2000);
                    if ($text !== null && $text !== '') {
                        $texts[] = $text;
                    }

                    if (count($texts) >= 20) {
                        break;
                    }
                }

                if ($texts !== []) {
                    return $this->capText(implode("\n", $texts), 8000);
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Read the `text` property of a response-like object.
     *
     * @param object $response Response object.
     * @return string|null
     */
    protected function readText(object $response): ?string
    {
        try {
            if (!isset($response->text)) {
                return null;
            }

            return $this->stringifyResult($response->text, 20000);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Read the shape of an embeddings response (`count × dimensions`) without
     * copying the float vectors themselves.
     *
     * @param object $response Response object.
     * @return array{count: int, dimensions: int|null}|null
     */
    protected function readEmbeddings(object $response): ?array
    {
        try {
            if (!isset($response->embeddings)) {
                return null;
            }

            $vectors = $this->toPlainList($response->embeddings);
            if ($vectors === []) {
                return null;
            }

            $first = $vectors[0];

            return [
                'count' => count($vectors),
                'dimensions' => is_array($first) ? count($first) : null,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Read tool calls from a response-like object's `toolCalls` property.
     *
     * @param object $response Response object.
     * @return list<array<string, mixed>>
     */
    protected function readToolCalls(object $response): array
    {
        $out = [];

        try {
            if (!isset($response->toolCalls)) {
                return [];
            }

            foreach ($this->toPlainList($response->toolCalls) as $toolCall) {
                $row = $this->readToolCall($toolCall);
                if ($row !== null) {
                    $out[] = $row;
                }
            }
        } catch (Throwable) {
            return [];
        }

        return $out;
    }

    /**
     * Normalize one tool-call-like object.
     *
     * @param mixed $toolCall Tool call object.
     * @return array{id: string|null, name: string|null, arguments: array<string, mixed>}|null
     */
    protected function readToolCall(mixed $toolCall): ?array
    {
        if (!is_object($toolCall)) {
            return null;
        }

        try {
            if (method_exists($toolCall, 'toArray')) {
                $array = $toolCall->toArray();
                if (!is_array($array)) {
                    return null;
                }

                return [
                    'id' => isset($array['id']) && is_scalar($array['id']) ? (string)$array['id'] : null,
                    'name' => isset($array['name']) && is_scalar($array['name']) ? (string)$array['name'] : null,
                    'arguments' => isset($array['arguments']) && is_array($array['arguments']) ? $array['arguments'] : [],
                ];
            }

            return [
                'id' => isset($toolCall->id) && is_scalar($toolCall->id) ? (string)$toolCall->id : null,
                'name' => isset($toolCall->name) && is_scalar($toolCall->name) ? (string)$toolCall->name : null,
                'arguments' => isset($toolCall->arguments) && is_array($toolCall->arguments) ? $toolCall->arguments : [],
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Read tool results from a response-like object's `toolResults` property.
     *
     * @param object $response Response object.
     * @return list<array<string, mixed>>
     */
    protected function readToolResults(object $response): array
    {
        $out = [];

        try {
            if (!isset($response->toolResults)) {
                return [];
            }

            foreach ($this->toPlainList($response->toolResults) as $toolResult) {
                $row = $this->readToolResult($toolResult);
                if ($row !== null) {
                    $out[] = $row;
                }
            }
        } catch (Throwable) {
            return [];
        }

        return $out;
    }

    /**
     * Normalize one tool-result-like object.
     *
     * @param mixed $toolResult Tool result object.
     * @return array{id: string|null, name: string|null, result_text: string|null, failed: bool, denied: bool}|null
     */
    protected function readToolResult(mixed $toolResult): ?array
    {
        if (!is_object($toolResult)) {
            return null;
        }

        try {
            if (method_exists($toolResult, 'toArray')) {
                $array = $toolResult->toArray();
                if (!is_array($array)) {
                    return null;
                }

                return [
                    'id' => isset($array['id']) && is_scalar($array['id']) ? (string)$array['id'] : null,
                    'name' => isset($array['name']) && is_scalar($array['name']) ? (string)$array['name'] : null,
                    'result_text' => array_key_exists('result', $array)
                        ? $this->stringifyResult($array['result'], 2000)
                        : null,
                    'failed' => ($array['failed'] ?? false) === true,
                    'denied' => ($array['denied'] ?? false) === true,
                ];
            }

            return [
                'id' => isset($toolResult->id) && is_scalar($toolResult->id) ? (string)$toolResult->id : null,
                'name' => isset($toolResult->name) && is_scalar($toolResult->name) ? (string)$toolResult->name : null,
                'result_text' => isset($toolResult->result)
                    ? $this->stringifyResult($toolResult->result, 2000)
                    : null,
                'failed' => ($toolResult->failed ?? false) === true,
                'denied' => ($toolResult->denied ?? false) === true,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Read step rows from a response-like object's `steps` property.
     *
     * @param object $response Response object.
     * @return list<array{text: string|null, tool_calls: list<array<string, mixed>>, usage: array<string, int>|null}>
     */
    protected function readSteps(object $response): array
    {
        $out = [];

        try {
            if (!isset($response->steps)) {
                return [];
            }

            foreach ($this->toPlainList($response->steps) as $step) {
                if (!is_object($step)) {
                    continue;
                }

                $array = method_exists($step, 'toArray') ? $step->toArray() : null;
                if (!is_array($array)) {
                    continue;
                }

                $toolCalls = [];
                foreach ($this->toPlainList($array['tool_calls'] ?? null) as $toolCall) {
                    $row = $this->readToolCall($toolCall);
                    if ($row !== null) {
                        $toolCalls[] = $row;
                    }
                }

                $out[] = [
                    'text' => isset($array['text']) ? $this->stringifyResult($array['text'], 8000) : null,
                    'tool_calls' => $toolCalls,
                    'usage' => isset($array['usage']) && is_object($array['usage'])
                        ? $this->normalizeUsage($array['usage'])
                        : null,
                ];
            }
        } catch (Throwable) {
            return [];
        }

        return array_slice($out, 0, 50);
    }

    /**
     * Read token usage from a response-like object's `usage` property.
     *
     * @param object $response Response object.
     * @return array{prompt: int, completion: int, cache_read: int, cache_write: int, reasoning: int}|null
     */
    protected function readUsage(object $response): ?array
    {
        try {
            if (!isset($response->usage) || !is_object($response->usage)) {
                return null;
            }

            return $this->normalizeUsage($response->usage);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Normalize a usage-like object to token counters.
     *
     * @param object $usage Usage object.
     * @return array{prompt: int, completion: int, cache_read: int, cache_write: int, reasoning: int}|null
     */
    protected function normalizeUsage(object $usage): ?array
    {
        try {
            $array = method_exists($usage, 'toArray') ? $usage->toArray() : null;
            if (is_array($array)) {
                return [
                    'prompt' => $this->toCounter($array['prompt_tokens'] ?? 0),
                    'completion' => $this->toCounter($array['completion_tokens'] ?? 0),
                    'cache_read' => $this->toCounter($array['cache_read_input_tokens'] ?? 0),
                    'cache_write' => $this->toCounter($array['cache_write_input_tokens'] ?? 0),
                    'reasoning' => $this->toCounter($array['reasoning_tokens'] ?? 0),
                ];
            }

            if (
                isset($usage->promptTokens) || isset($usage->completionTokens)
                || isset($usage->cacheReadInputTokens) || isset($usage->cacheWriteInputTokens)
                || isset($usage->reasoningTokens)
            ) {
                return [
                    'prompt' => $this->toCounter($usage->promptTokens ?? 0),
                    'completion' => $this->toCounter($usage->completionTokens ?? 0),
                    'cache_read' => $this->toCounter($usage->cacheReadInputTokens ?? 0),
                    'cache_write' => $this->toCounter($usage->cacheWriteInputTokens ?? 0),
                    'reasoning' => $this->toCounter($usage->reasoningTokens ?? 0),
                ];
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Coerce a counter value to int.
     *
     * @param mixed $value Counter value.
     * @return int
     */
    protected function toCounter(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }

    /**
     * Convert an array, Cake collection or traversable to a plain list.
     *
     * @param mixed $value List-like value.
     * @return list<mixed>
     */
    protected function toPlainList(mixed $value): array
    {
        try {
            if (is_array($value)) {
                return array_values($value);
            }

            if (!is_object($value)) {
                return [];
            }

            if ($value instanceof CollectionInterface) {
                return array_values($value->toList());
            }

            if (method_exists($value, 'toArray')) {
                $array = $value->toArray();

                return is_array($array) ? array_values($array) : [];
            }

            if ($value instanceof Traversable) {
                return array_values(iterator_to_array($value));
            }
        } catch (Throwable) {
            return [];
        }

        return [];
    }

    /**
     * Stringify a result-ish value, capped to keep entries storable.
     *
     * Strings pass through, `Stringable` objects are cast, scalars become
     * strings; anything else (arrays, arbitrary objects) returns null so the
     * full value stays only in the serialized `payload`.
     *
     * @param mixed $value Result value.
     * @param int $limit Max characters kept.
     * @return string|null
     */
    protected function stringifyResult(mixed $value, int $limit = 20000): ?string
    {
        try {
            if (is_string($value)) {
                $text = $value;
            } elseif ($value instanceof Stringable) {
                $text = (string)$value;
            } elseif (is_scalar($value)) {
                $text = (string)$value;
            } else {
                return null;
            }

            return $this->capText($text, $limit);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Cap text without splitting a trailing multibyte sequence.
     *
     * @param string $text Text value.
     * @param int $limit Max characters kept.
     * @return string
     */
    protected function capText(string $text, int $limit): string
    {
        if (strlen($text) <= $limit) {
            return $text;
        }

        $cut = substr($text, 0, $limit);
        while ($cut !== '' && !preg_match('//u', $cut)) {
            $cut = substr($cut, 0, -1);
        }

        return $cut . '…';
    }

    /**
     * Normalize the payload so the frontend has a stable location for the agent
     * and model, regardless of which event produced it.
     *
     * Different events nest the agent/model differently (step events expose
     * `agent` at the payload root; streamed events bury it under
     * `prompt.properties.agent`, and the model may only live on that agent).
     * Copy the agent to `payload.agent` when missing and backfill `content.model`
     * / `content.provider` from the nested agent so listings and panels stay
     * consistent. The raw, fully-serialized payload is preserved untouched.
     *
     * @param array<string, mixed> $content Entry content (mutated in place).
     * @return void
     */
    protected function normalizePayload(array &$content): void
    {
        $payload = $content['payload'] ?? [];
        if (!is_array($payload)) {
            return;
        }

        $agent =
            $payload['agent']
            ?? $payload['prompt']['agent']
            ?? $payload['prompt']['properties']['agent']
            ?? null;

        if (is_array($agent) && !isset($payload['agent'])) {
            $payload['agent'] = $agent;
        }

        $agentProps = is_array($agent) ? ($agent['properties'] ?? []) : [];

        if (
            ($content['model'] ?? null) === null
            && isset($agentProps['model'])
            && is_scalar($agentProps['model'])
        ) {
            $content['model'] = $agentProps['model'];
        }

        if (
            ($content['provider'] ?? null) === null
            && isset($agentProps['provider'])
        ) {
            $content['provider'] = is_object($agentProps['provider'])
                ? $this->shortClass($agentProps['provider'])
                : $agentProps['provider'];
        }

        $content['payload'] = $payload;
    }

    /**
     * Short class name (without namespace) for an object.
     *
     * @param object $object Object instance.
     * @return string
     */
    protected function shortClass(object $object): string
    {
        $class = $object::class;
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }

    /**
     * Recursively serialize event payload values for storage.
     *
     * Objects become `{ class, properties }` where `properties` is the curated
     * `toArray()` when available, otherwise all instance properties (public,
     * protected and private) read via reflection so debug-relevant data is not
     * lost. Framework objects (`Cake\*`) and any cyclic reference are collapsed to
     * their class name only — this avoids dumping the global `EventManager`
     * listener graph or provider/gateway cycles. Throwables become
     * `{ class, message }`. The final tree is run through `SensitiveData` to redact
     * secrets (api keys, tokens, …).
     *
     * @param array<string, mixed> $data Event payload.
     * @return array<string, mixed>
     */
    protected function extractPayload(array $data): array
    {
        $patterns = array_values(array_unique(array_merge(
            SensitiveData::DEFAULT_PARAMETER_PATTERNS,
            ['*key*', '*api*', '*token*', '*secret*', '*password*'],
        )));

        $seen = new SplObjectStorage();

        return SensitiveData::parameters(
            $this->serializeValue($data, 0, $seen),
            $patterns,
            [
                'usage',
                'continuation_token',
            ],
        );
    }

    /**
     * Recursively serialize a payload value.
     *
     * @param mixed $value Value to serialize.
     * @param int $depth Recursion depth guard.
     * @param \SplObjectStorage<object, null> $seen Visited objects (cycle guard).
     * @return mixed
     */
    protected function serializeValue(mixed $value, int $depth, SplObjectStorage $seen): mixed
    {
        if ($depth > 8) {
            return is_object($value) ? $value::class : $value;
        }

        if (is_object($value)) {
            if ($value instanceof Throwable) {
                return [
                    'class' => $value::class,
                    'message' => $value->getMessage(),
                ];
            }

            $class = $value::class;

            if ($value instanceof CollectionInterface) {
                return $this->serializeValue($value->toArray(), $depth + 1, $seen);
            }

            if (str_starts_with($class, 'Cake\\') || $seen->offsetExists($value)) {
                return ['class' => $class];
            }

            $seen->offsetSet($value);

            $properties = method_exists($value, 'toArray')
                ? $value->toArray()
                : $this->objectVars($value);

            return [
                'class' => $class,
                'properties' => $this->serializeValue($properties, $depth + 1, $seen),
            ];
        }

        if (is_array($value)) {
            return array_map(
                fn($item): mixed => $this->serializeValue($item, $depth + 1, $seen),
                $value,
            );
        }

        return $value;
    }

    /**
     * Read all instance properties (public, protected, private) of an object.
     *
     * @param object $value Object instance.
     * @return array<string, mixed>
     */
    protected function objectVars(object $value): array
    {
        $reflection = new ReflectionClass($value);
        $out = [];

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();
            if (!$property->isInitialized($value)) {
                $out[$name] = null;

                continue;
            }

            $out[$name] = $property->getValue($value);
        }

        return $out;
    }
}
