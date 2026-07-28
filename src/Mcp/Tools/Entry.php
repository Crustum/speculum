<?php
declare(strict_types=1);

namespace Crustum\Speculum\Mcp\Tools;

use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;
use Crustum\Speculum\Mcp\EntryPresenter;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Override;
use Throwable;

/**
 * Fetch one Speculum entry with optional related or family expansion.
 */
#[IsReadOnly]
class Entry extends Tool
{
    /**
     * MCP tool name.
     *
     * @var string
     */
    protected string $name = 'speculum_entry';

    /**
     * MCP tool description.
     *
     * @var string
     */
    protected string $description = 'Get one Speculum entry by id from speculum_search. Content is truncated. Use expand to attach related entries: related = other entries from the same request story; family = other occurrences of the same exception throw site.';

    /**
     * @inheritDoc
     */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Entry id from a speculum_search summary.')
                ->required(),
            'expand' => $schema->string()
                ->description(
                    'none: entry only (default). '
                    . 'related: summaries of other entries from the same request story (same batch_id). '
                    . 'family: summaries of other times this same exception occurred, across requests.',
                )
                ->enum(['none', 'related', 'family']),
        ];
    }

    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request.
     * @return \Crustum\Mcp\Response Entry detail.
     */
    public function handle(Request $request): Response
    {
        $id = $request->get('id');
        if (!is_string($id) || $id === '') {
            return Response::error('The "id" argument is required.');
        }

        try {
            $entry = Speculum::getRepository()->find($id);
        } catch (Throwable $throwable) {
            return Response::error($throwable->getMessage());
        }

        $expand = $request->get('expand');
        $expandMode = is_string($expand) && $expand !== '' ? $expand : 'none';

        $payload = [
            'entry' => EntryPresenter::detail($entry),
        ];

        if ($expandMode === 'related') {
            $payload['related'] = $this->expandRelated(
                null,
                (new EntryQueryOptions())->batchId($entry->batchId)->limit(EntryPresenter::MAX_LIMIT),
            );
        }

        if ($expandMode === 'family') {
            if ($entry->familyHash === null || $entry->familyHash === '') {
                $payload['family'] = [
                    'counts' => [],
                    'entries' => [],
                    'note' => 'Entry has no family_hash.',
                ];
            } else {
                $payload['family'] = $this->expandRelated(
                    $entry->type,
                    (new EntryQueryOptions())
                        ->familyHash($entry->familyHash)
                        ->limit(EntryPresenter::MAX_LIMIT),
                );
            }
        }

        return Response::json($payload);
    }

    /**
     * Load related entries as type counts plus summaries.
     *
     * @param string|null $type Optional type filter.
     * @param \Crustum\Speculum\Storage\EntryQueryOptions $options Query options.
     * @return array{counts: array<string, int>, entries: list<array<string, mixed>>}
     */
    protected function expandRelated(?string $type, EntryQueryOptions $options): array
    {
        $related = Speculum::getRepository()->get($type, $options);
        $counts = [];
        $summaries = [];

        foreach ($related as $item) {
            $counts[$item->type] = ($counts[$item->type] ?? 0) + 1;
            $summaries[] = EntryPresenter::summarize($item);
        }

        return [
            'counts' => $counts,
            'entries' => $summaries,
        ];
    }
}
