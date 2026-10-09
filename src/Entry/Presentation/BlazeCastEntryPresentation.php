<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Crustum\Speculum\Enum\EntryType;
use Override;

/**
 * BlazeCast entry presentation (serves deliveries and messages).
 */
class BlazeCastEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'BlazeCast realtime deliveries and messages.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $content = $entry->content;
        $summary = static::direction($entry) . ' ' . ($content['event'] ?? '')
            . ' on ' . ($content['channel'] ?? '');

        return $full ? $summary : static::limit($summary, 80);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Direction', 'Event', 'Channel', 'Detail', 'Created'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableRow(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;
        $channel = (string)($content['channel'] ?? '');
        $detail = static::detail($content);

        return [
            static::shortUuid((string)$entry->id),
            static::direction($entry),
            $full ? (string)($content['event'] ?? '') : static::limit($content['event'] ?? null, 40),
            $full ? $channel : static::limit($channel !== '' ? $channel : null, 30),
            $full ? $detail : static::limit($detail !== '' ? $detail : null, 28),
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
        $connectionIds = $content['connection_ids'] ?? null;

        return [
            'label' => 'BlazeCast',
            'subtitle' => '',
            'fields' => [
                'Direction' => static::direction($entry),
                'Event' => (string)($content['event'] ?? ''),
                'Channel' => (string)($content['channel'] ?? ''),
                'App' => (string)($content['app_id'] ?? ''),
                'Connection' => (string)($content['connection_id'] ?? ''),
                'Delivered To' => isset($content['delivered_to']) ? (string)$content['delivered_to'] : '',
                'Connections' => is_array($connectionIds) ? implode(', ', $connectionIds) : '',
                'Correlation' => (string)($content['correlation_id'] ?? ''),
            ],
            'list' => null,
            'blocks' => [
                'Payload' => static::blockText($content['payload'] ?? null),
            ],
        ];
    }

    /**
     * Resolve message direction from type or content.
     *
     * @param \Crustum\Speculum\Entry\EntryResult $entry Entry result.
     * @return string
     */
    protected static function direction(EntryResult $entry): string
    {
        if ($entry->type === EntryType::BlazeCastMessage->value) {
            return 'Incoming';
        }

        return ($entry->content['direction'] ?? '') === 'in' ? 'Incoming' : 'Outgoing';
    }

    /**
     * Resolve the detail cell (connection id for messages, delivered count otherwise).
     *
     * @param array<string, mixed> $content Entry content.
     * @return string
     */
    protected static function detail(array $content): string
    {
        if (isset($content['connection_id'])) {
            return (string)$content['connection_id'];
        }

        return isset($content['delivered_to']) ? (string)$content['delivered_to'] : '';
    }
}
