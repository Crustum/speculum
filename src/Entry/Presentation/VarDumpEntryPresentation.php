<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * VarDump entry presentation.
 */
class VarDumpEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'Dumped variables with source location.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $summary = static::summaryText($entry->content);

        return $full ? $summary : static::limit($summary !== '' ? $summary : null, 80);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Summary', 'Location', 'Dumps', 'Created'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableRow(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;
        $location = static::location($content);
        $summary = static::summaryText($content);

        return [
            static::shortUuid((string)$entry->id),
            $full ? $summary : static::limit($summary !== '' ? $summary : null, 40),
            $full ? $location : static::limit($location !== '' ? $location : null, 40),
            (string)count(static::dumps($content)),
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

        return [
            'label' => 'Var Dump',
            'subtitle' => '',
            'fields' => [
                'Summary' => static::summaryText($content),
                'File' => static::location($content),
                'Entry Point' => (string)($content['entry_point_description'] ?? ''),
                'Entry Point Type' => (string)($content['entry_point_type'] ?? ''),
                'Dumps' => (string)count(static::dumps($content)),
            ],
            'list' => null,
            'blocks' => [],
        ];
    }

    /**
     * Collect dump HTML payloads from content.
     *
     * @param array<string, mixed> $content Entry content.
     * @return list<string>
     */
    protected static function dumps(array $content): array
    {
        $dumps = $content['vardumps'] ?? [];
        if (is_array($dumps) && $dumps !== []) {
            return array_values($dumps);
        }

        foreach (['vardump', 'dump'] as $key) {
            if (isset($content[$key])) {
                return [(string)$content[$key]];
            }
        }

        return [];
    }

    /**
     * Resolve display text: recorded summary, else first dump as plain text.
     *
     * @param array<string, mixed> $content Entry content.
     * @return string
     */
    protected static function summaryText(array $content): string
    {
        $summary = (string)($content['summary'] ?? '');
        if ($summary !== '') {
            return $summary;
        }

        foreach (static::dumps($content) as $dump) {
            $text = trim(strip_tags($dump));
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    /**
     * Format the dump source location.
     *
     * @param array<string, mixed> $content Entry content.
     * @return string
     */
    protected static function location(array $content): string
    {
        return isset($content['file'])
            ? $content['file'] . ':' . ($content['line'] ?? '')
            : '';
    }
}
