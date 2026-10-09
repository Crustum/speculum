<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * Command entry presentation.
 */
class CommandEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'Console command runs.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        return (string)($entry->content['command'] ?? '');
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Command', 'Exit Code', 'Created'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableRow(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;
        $command = (string)($content['command'] ?? '');

        return [
            static::shortUuid((string)$entry->id),
            $full ? $command : static::limit($command, 50),
            (string)($content['exit_code'] ?? ''),
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
            'label' => 'Command',
            'subtitle' => (string)($content['command'] ?? ''),
            'fields' => [
                'Exit Code' => (string)($content['exit_code'] ?? ''),
            ],
            'list' => null,
            'blocks' => [
                'Arguments' => static::blockText($content['arguments'] ?? null),
                'Options' => static::blockText($content['options'] ?? null),
            ],
        ];
    }
}
