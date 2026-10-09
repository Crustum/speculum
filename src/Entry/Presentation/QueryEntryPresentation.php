<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * Query entry presentation.
 */
class QueryEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'Executed SQL queries with bindings and timing.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $content = $entry->content;
        $sql = (string)($content['sql'] ?? '');

        return ($full ? $sql : static::limit($sql, 60))
            . ' (' . ($content['time'] ?? '') . 'ms)';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'SQL', 'Time', 'Slow', 'Connection', 'Created'];
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
            $full ? (string)($content['sql'] ?? '') : static::limit($content['sql'] ?? null, 60),
            static::unit($content['time'] ?? null, 'ms'),
            empty($content['slow']) ? 'No' : '<error>Yes</error>',
            (string)($content['connection'] ?? ''),
            static::humanTime($entry->createdAt),
        ];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function batchHeaders(): array
    {
        return ['#', 'UUID', 'Time', 'SQL', 'Source', 'Flags'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function batchRow(EntryResult $entry, int $index, array $flags = [], bool $full = false): array
    {
        $content = $entry->content;
        $source = isset($content['file'])
            ? $content['file'] . ':' . ($content['line'] ?? '')
            : '';

        return [
            (string)$index,
            static::shortUuid((string)$entry->id),
            $full
                ? static::unit($content['time'] ?? null, 'ms')
                : static::limit(static::unit($content['time'] ?? null, 'ms'), 10),
            $full ? (string)($content['sql'] ?? '') : static::limit($content['sql'] ?? null, 60),
            $full ? $source : static::limit($source !== '' ? $source : null, 30),
            implode(', ', $flags),
        ];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function detailFields(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;
        $source = isset($content['file'])
            ? $content['file'] . ':' . ($content['line'] ?? '')
            : '';

        return [
            'label' => 'Query',
            'subtitle' => '',
            'fields' => [
                'Connection' => (string)($content['connection'] ?? ''),
                'Duration' => static::unit($content['time'] ?? null, 'ms')
                    . (empty($content['slow']) ? '' : '  <error>SLOW</error>'),
                'Source' => $source,
                'SQL' => (string)($content['sql'] ?? ''),
            ],
            'list' => null,
            'blocks' => [
                'Bindings' => static::blockText($content['bindings'] ?? null),
            ],
        ];
    }
}
