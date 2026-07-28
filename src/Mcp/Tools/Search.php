<?php
declare(strict_types=1);

namespace Crustum\Speculum\Mcp\Tools;

use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;
use Crustum\Speculum\Mcp\EntryPresenter;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Override;

/**
 * Search Speculum entries with filters; returns summaries plus meta footer.
 */
#[IsReadOnly]
class Search extends Tool
{
    /**
     * MCP tool name.
     *
     * @var string
     */
    protected string $name = 'speculum_search';

    /**
     * MCP tool description.
     *
     * @var string
     */
    protected string $description = 'Search Speculum entries. Returns short summaries and recording meta. Pass a summary `id` to speculum_entry for detail. Filters: type, tag, batch_id, family_hash, since_sequence, limit.';

    /**
     * @inheritDoc
     */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->description('Entry type: request, exception, query, job, log, mail, …'),
            'tag' => $schema->string()
                ->description('Tag filter; comma-separated for multiple.'),
            'batch_id' => $schema->string()
                ->description('Batch id for one request story.'),
            'family_hash' => $schema->string()
                ->description('Exception family hash for related occurrences.'),
            'since_sequence' => $schema->integer()
                ->description('Return entries with sequence greater than this checkpoint.'),
            'limit' => $schema->integer()
                ->description('Max summaries; default 15, max 50.'),
        ];
    }

    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request.
     * @return \Crustum\Mcp\Response Search results.
     */
    public function handle(Request $request): Response
    {
        $type = $request->get('type');
        $typeFilter = is_string($type) && $type !== '' ? $type : null;

        $options = (new EntryQueryOptions())
            ->limit(EntryPresenter::clampLimit($request->get('limit')));

        $tag = $request->get('tag');
        if (is_string($tag) && $tag !== '') {
            $options->tag($tag);
        }

        $batchId = $request->get('batch_id');
        if (is_string($batchId) && $batchId !== '') {
            $options->batchId($batchId);
        }

        $familyHash = $request->get('family_hash');
        if (is_string($familyHash) && $familyHash !== '') {
            $options->familyHash($familyHash);
        }

        $since = $request->get('since_sequence');
        if ($since !== null && $since !== '') {
            $options->afterSequence($since);
        }

        $entries = Speculum::getRepository()->get($typeFilter, $options);
        $summaries = array_map(
            EntryPresenter::summarize(...),
            $entries,
        );

        return Response::json([
            'entries' => $summaries,
            'count' => count($summaries),
            'meta' => [
                'recording' => Speculum::isRecording(),
                'available_watchers' => WatcherRegistry::availableWatchers(),
            ],
        ]);
    }
}
