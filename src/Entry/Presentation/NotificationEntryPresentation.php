<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * Notification entry presentation.
 */
class NotificationEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'Sent notifications with recipients.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $content = $entry->content;
        $summary = ($content['notification'] ?? '') . ' → ' . ($content['notifiable'] ?? '');

        return $full ? $summary : static::limit($summary, 80);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Notification', 'Channel', 'Recipient', 'Queued', 'Created'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableRow(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;
        $notifiable = (string)($content['notifiable'] ?? '');

        return [
            static::shortUuid((string)$entry->id),
            $full
                ? static::classBasename((string)($content['notification'] ?? ''))
                : static::limit(
                    ($content['notification'] ?? null) !== null
                        ? static::classBasename((string)$content['notification'])
                        : null,
                    40,
                ),
            (string)($content['channel'] ?? ''),
            $full ? $notifiable : static::limit($notifiable !== '' ? $notifiable : null, 30),
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
            'label' => 'Notification',
            'subtitle' => '',
            'fields' => [
                'Channel' => (string)($content['channel'] ?? ''),
                'Notification' => (string)($content['notification'] ?? ''),
                'Queued' => empty($content['queued']) ? 'No' : 'Yes',
                'Notifiable' => (string)($content['notifiable'] ?? ''),
            ],
            'list' => null,
            'blocks' => [
                'Response' => static::blockText($content['response'] ?? null),
            ],
        ];
    }
}
