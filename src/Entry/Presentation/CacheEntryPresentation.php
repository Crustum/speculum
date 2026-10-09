<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * Cache entry presentation.
 */
class CacheEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'Cache hits, misses and writes.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $content = $entry->content;

        return ($content['type'] ?? '') . ' ' . ($content['key'] ?? '');
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Action', 'Key', 'Created'];
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
            static::colorCacheAction((string)($content['type'] ?? '')),
            static::limit($content['key'] ?? null, 50),
            static::humanTime($entry->createdAt),
        ];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function batchHeaders(): array
    {
        return ['Action', 'Key'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function batchRow(EntryResult $entry, int $index, array $flags = [], bool $full = false): array
    {
        $content = $entry->content;

        return [
            (string)($content['type'] ?? ''),
            (string)($content['key'] ?? ''),
        ];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function detailFields(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;

        return [
            'label' => 'Cache',
            'subtitle' => (string)($content['type'] ?? ''),
            'fields' => [
                'Key' => (string)($content['key'] ?? ''),
                'Expiration' => static::unit($content['expiration'] ?? null, 's'),
            ],
            'list' => null,
            'blocks' => [
                'Value' => static::blockText($content['value'] ?? null),
            ],
        ];
    }

    /**
     * Colorize a cache action for console output.
     *
     * @param string $type Cache action.
     * @return string
     */
    protected static function colorCacheAction(string $type): string
    {
        return match ($type) {
            'hit' => '<success>HIT</success>',
            'missed' => '<error>MISS</error>',
            'set' => '<info>SET</info>',
            'forget' => '<warning>FORGET</warning>',
            default => $type,
        };
    }
}
