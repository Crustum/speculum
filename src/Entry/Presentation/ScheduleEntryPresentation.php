<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * Scheduled task entry presentation.
 */
class ScheduleEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'Scheduled task runs with output.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $summary = (string)($entry->content['description'] ?? $entry->content['command'] ?? '');

        return $full ? $summary : static::limit($summary, 80);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Command', 'Expression', 'Timezone', 'Created'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableRow(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;
        $command = (string)($content['description'] ?? $content['command'] ?? '');

        return [
            static::shortUuid((string)$entry->id),
            $full ? $command : static::limit($command !== '' ? $command : null, 40),
            $full ? (string)($content['expression'] ?? '') : static::limit($content['expression'] ?? null, 20),
            (string)($content['timezone'] ?? ''),
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
            'label' => 'Schedule',
            'subtitle' => '',
            'fields' => [
                'Description' => (string)($content['description'] ?? ''),
                'Command' => (string)($content['command'] ?? ''),
                'Expression' => (string)($content['expression'] ?? ''),
                'User' => (string)($content['user'] ?? ''),
                'Timezone' => (string)($content['timezone'] ?? ''),
            ],
            'list' => null,
            'blocks' => [
                'Output' => static::blockText($content['output'] ?? null),
            ],
        ];
    }
}
