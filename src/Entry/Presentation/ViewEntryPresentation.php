<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * View entry presentation.
 */
class ViewEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'Rendered views with data.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $summary = (string)($entry->content['name'] ?? '');

        return $full ? $summary : static::limit($summary, 80);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'View', 'Composers', 'Created'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableRow(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;
        $composers = $content['composers'] ?? null;

        return [
            static::shortUuid((string)$entry->id),
            $full ? (string)($content['name'] ?? '') : static::limit($content['name'] ?? null, 50),
            is_array($composers) ? (string)count($composers) : '0',
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
        $data = $content['data'] ?? null;
        $composers = $content['composers'] ?? null;

        return [
            'label' => 'View',
            'subtitle' => '',
            'fields' => [
                'View' => (string)($content['name'] ?? ''),
                'Path' => (string)($content['path'] ?? ''),
                'Data' => is_array($data) ? implode(', ', $data) : '',
                'Composers' => is_array($composers) ? (string)count($composers) : '0',
            ],
            'list' => null,
            'blocks' => [
                'Data' => static::blockText($data),
            ],
        ];
    }
}
