<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * Explorator search/index-write entry presentation.
 */
class ExploratorEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'Search queries and index writes.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $content = $entry->content;
        $summary = ($content['operation'] ?? 'search') . ': ' . ($content['query'] ?? '');

        return $full ? $summary : static::limit($summary, 80);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Operation', 'Query', 'Engine', 'Hits', 'Created'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableRow(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;
        $hits = $content['hits'] ?? $content['count'] ?? '';

        return [
            static::shortUuid((string)$entry->id),
            (string)($content['operation'] ?? 'search'),
            $full ? (string)($content['query'] ?? '') : static::limit($content['query'] ?? null, 50),
            (string)($content['engine'] ?? ''),
            $hits === '' ? '' : (string)$hits,
            static::humanTime($entry->createdAt),
        ];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function detailFields(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;
        $hits = $content['hits'] ?? $content['count'] ?? null;

        return [
            'label' => 'Search',
            'subtitle' => '',
            'fields' => [
                'Operation' => (string)($content['operation'] ?? 'search'),
                'Query' => (string)($content['query'] ?? ''),
                'Engine' => (string)($content['engine'] ?? ''),
                'Index' => (string)($content['index'] ?? ''),
                'Table' => (string)($content['table'] ?? ''),
                'Hits' => $hits === null ? '' : (string)$hits,
                'Duration' => static::unit($content['time'] ?? null, 'ms')
                    . (empty($content['slow']) ? '' : '  <error>SLOW</error>'),
            ],
            'list' => null,
            'blocks' => [
                'Request' => static::blockText($content['request'] ?? null),
                'Response' => static::blockText($content['response'] ?? null),
            ],
        ];
    }
}
