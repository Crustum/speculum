<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * Broadcast entry presentation.
 */
class BroadcastEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'Broadcast events with channels.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $content = $entry->content;
        $summary = ($content['event'] ?? '') . ' → ' . static::channels($content);

        return $full ? $summary : static::limit($summary, 80);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Event', 'Channels', 'Connection', 'Queued', 'Created'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableRow(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;
        $channels = static::channels($content);

        return [
            static::shortUuid((string)$entry->id),
            $full ? (string)($content['event'] ?? '') : static::limit($content['event'] ?? null, 40),
            $full ? $channels : static::limit($channels !== '' ? $channels : null, 40),
            (string)($content['connection'] ?? ''),
            empty($content['queued']) ? 'No' : 'Yes',
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
            'label' => 'Broadcast',
            'subtitle' => '',
            'fields' => [
                'Event' => (string)($content['event'] ?? ''),
                'Channels' => static::channels($content),
                'Connection' => (string)($content['connection'] ?? ''),
                'Queued' => empty($content['queued']) ? 'No' : 'Yes',
                'Correlation' => (string)($content['correlation_id'] ?? ''),
            ],
            'list' => null,
            'blocks' => [
                'Payload' => static::blockText($content['payload'] ?? null),
            ],
        ];
    }

    /**
     * Join broadcast channels for display.
     *
     * @param array<string, mixed> $content Entry content.
     * @return string
     */
    protected static function channels(array $content): string
    {
        $channels = $content['channels'] ?? [];

        return is_array($channels) ? implode(', ', $channels) : '';
    }
}
