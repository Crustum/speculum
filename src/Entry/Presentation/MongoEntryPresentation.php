<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * Mongo command entry presentation.
 */
class MongoEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'MongoDB commands with duration.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $summary = (string)($entry->content['summary'] ?? $entry->content['command'] ?? '');

        return $full ? $summary : static::limit($summary, 80);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Command', 'Database', 'Duration', 'Created'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableRow(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;
        $command = (string)($content['summary'] ?? $content['command'] ?? '');

        return [
            static::shortUuid((string)$entry->id),
            $full ? $command : static::limit($command !== '' ? $command : null, 50),
            (string)($content['database'] ?? ''),
            static::unit($content['time'] ?? null, 'ms')
                . (empty($content['failed']) ? '' : '  <error>FAILED</error>')
                . (!empty($content['failed']) || empty($content['slow']) ? '' : '  <error>SLOW</error>'),
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
            'label' => 'Mongo',
            'subtitle' => '',
            'fields' => [
                'Command' => (string)($content['command'] ?? ''),
                'Database' => (string)($content['database'] ?? ''),
                'Collection' => (string)($content['collection'] ?? ''),
                'Duration' => static::unit($content['time'] ?? null, 'ms')
                    . (empty($content['slow']) ? '' : '  <error>SLOW</error>'),
                'Failed' => empty($content['failed']) ? 'No' : '<error>Yes</error>',
            ],
            'list' => null,
            'blocks' => [
                'Payload' => static::blockText($content['payload'] ?? null),
            ],
        ];
    }
}
