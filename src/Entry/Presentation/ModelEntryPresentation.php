<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * Model entry presentation.
 */
class ModelEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'Model lifecycle events and hydrations.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $content = $entry->content;
        $summary = ucfirst((string)($content['action'] ?? '')) . ' ' . ($content['model'] ?? '');

        return $full ? $summary : static::limit($summary, 80);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Model', 'Action', 'Changes', 'Created'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableRow(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;
        $action = (string)($content['action'] ?? '');
        $changes = $content['changes'] ?? null;

        return [
            static::shortUuid((string)$entry->id),
            $full ? (string)($content['model'] ?? '') : static::limit($content['model'] ?? null, 50),
            $action === 'deleted' ? '<error>Deleted</error>' : ucfirst($action),
            is_array($changes) ? (string)count($changes) : '',
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
        $changes = $content['changes'] ?? null;

        return [
            'label' => 'Model',
            'subtitle' => '',
            'fields' => [
                'Model' => (string)($content['model'] ?? ''),
                'Action' => ucfirst((string)($content['action'] ?? '')),
                'Hydrated' => isset($content['count']) ? (string)$content['count'] : '',
            ],
            'list' => null,
            'blocks' => [
                'Changes' => static::blockText($changes),
            ],
        ];
    }
}
