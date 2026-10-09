<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * Event entry presentation.
 */
class EventEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'Dispatched framework events.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $content = $entry->content;
        $name = (string)($content['name'] ?? '');

        if (isset($content['listeners']) && is_array($content['listeners'])) {
            $name .= ' (' . count($content['listeners']) . ' listeners)';
        }

        return $full ? $name : static::limit($name, 80);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Name', 'Created'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableRow(EntryResult $entry, bool $full = false): array
    {
        $name = (string)($entry->content['name'] ?? '');

        return [
            static::shortUuid((string)$entry->id),
            $full ? $name : static::limit($name, 60),
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
            'label' => 'Event',
            'subtitle' => (string)($content['name'] ?? ''),
            'fields' => [
                'Name' => (string)($content['name'] ?? ''),
                'Broadcast' => empty($content['broadcast']) ? 'No' : 'Yes',
            ],
            'list' => static::eventListeners($content['listeners'] ?? null),
            'blocks' => [
                'Payload' => static::blockText($content['payload'] ?? null),
            ],
        ];
    }

    /**
     * Format event listeners as a listing.
     *
     * @param mixed $listeners Listeners.
     * @return array{label: string, items: list<string>, more: int, moreLabel: string}|null
     */
    protected static function eventListeners(mixed $listeners): ?array
    {
        if (!is_array($listeners) || $listeners === []) {
            return null;
        }

        return [
            'label' => 'Listeners',
            'items' => array_map(static fn(mixed $listener): string => (string)$listener, array_values($listeners)),
            'more' => 0,
            'moreLabel' => '',
        ];
    }
}
