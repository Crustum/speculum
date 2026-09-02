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
use Throwable;

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
        'Ai.agentFailedEvent',
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
        'agentFailedEvent' => 'agent',
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
        'agentFailedEvent',
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

        $content = [
            'category' => $category,
            'name' => $name,
            'invocationId' => $data['invocationId'] ?? null,
            'provider' => isset($data['provider']) ? $this->shortClass($data['provider']) : null,
            'model' => $data['model'] ?? null,
            'tool' => isset($data['tool'])
                ? (is_object($data['tool']) ? $this->toolClass($data['tool']) : $data['tool'])
                : null,
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
            'summary' => $this->summary($category, $short, $data),
        ];

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
     * @return string
     */
    protected function summary(string $category, string $short, array $data): string
    {
        return match ($category) {
            'generation' => ($data['model'] ?? 'generation') . ' · ' . $short,
            'tool' => ($data['tool'] !== null ? $this->toolClass($data['tool']) : 'tool') . ' · ' . $short,
            'failover' => ($data['from'] ?? '?') . ' → ' . ($data['to'] ?? '?'),
            'store' => ($data['name'] ?? $data['storeId'] ?? 'store'),
            'file' => ($data['fileId'] ?? $data['storeId'] ?? 'file'),
            default => $short . (isset($data['stepNumber']) ? ' · step ' . $data['stepNumber'] : ''),
        };
    }

    /**
     * Resolve the short class name of the tool behind a (possibly wrapped) tool.
     *
     * Some integrations decorate tools (e.g. aicoder's `EventedTool` wraps the
     * real tool in an `inner` property). Surface the real tool for display while
     * the raw wrapper is still preserved in `payload`.
     *
     * @param object $tool Tool instance (possibly a wrapper).
     * @return string
     */
    protected function toolClass(object $tool): string
    {
        $inner = null;

        try {
            if (method_exists($tool, 'getInner')) {
                $inner = $tool->getInner();
            } elseif (property_exists($tool, 'inner')) {
                $inner = $tool->inner ?? null;
            }
        } catch (Throwable) {
            $inner = null;
        }

        return is_object($inner) ? $this->shortClass($inner) : $this->shortClass($tool);
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
                'inputTokens',
                'outputTokens',
                'cacheReadTokens',
                'cacheWriteTokens',
                'maxTokens',
                'promptTokens',
                'completionTokens',
                'totalTokens',
                'cachedTokens',
                'cacheCreationTokens',
                'cacheReadInputTokens',
                'cacheCreationInputTokens',
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
