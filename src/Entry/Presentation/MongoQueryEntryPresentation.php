<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Crustum\Speculum\Enum\EntryType;
use Override;

/**
 * Mongo query entry presentation (serves driver queries and query logs).
 */
class MongoQueryEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'MongoDB queries with source location.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $summary = (string)($entry->content['query'] ?? '');

        return $full ? $summary : static::limit($summary, 80);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Query', 'Connection', 'Duration', 'Created'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableRow(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;

        return [
            static::shortUuid((string)$entry->id),
            $full ? (string)($content['query'] ?? '') : static::limit($content['query'] ?? null, 50),
            (string)($content['connection'] ?? ''),
            static::unit($content['time'] ?? null, 'ms')
                . (empty($content['slow']) ? '' : '  <error>SLOW</error>'),
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
        $location = isset($content['file'])
            ? $content['file'] . ':' . ($content['line'] ?? '')
            : '';

        return [
            'label' => $entry->type === EntryType::MongoQueryLog->value ? 'Mongo Query Log' : 'Mongo Query',
            'subtitle' => '',
            'fields' => [
                'Connection' => (string)($content['connection'] ?? ''),
                'Operation' => (string)($content['operation'] ?? ''),
                'Database' => (string)($content['database'] ?? ''),
                'Collection' => (string)($content['collection'] ?? ''),
                'Location' => $location,
                'Duration' => static::unit($content['time'] ?? null, 'ms')
                    . (empty($content['slow']) ? '' : '  <error>SLOW</error>'),
                'Query' => (string)($content['query'] ?? ''),
            ],
            'list' => null,
            'blocks' => [],
        ];
    }
}
