<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * Exception entry presentation.
 */
class ExceptionEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'Recorded exceptions with stack traces.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $content = $entry->content;
        $text = ($content['class'] ?? '') . ': ' . ($content['message'] ?? '');

        return $full ? $text : static::limit($text, 80);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Class', 'Message', 'Occurrences', 'Created'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableRow(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;

        return [
            static::shortUuid((string)$entry->id),
            static::classBasename((string)($content['class'] ?? '')),
            $full ? (string)($content['message'] ?? '') : static::limit($content['message'] ?? null, 50),
            (string)($content['occurrences'] ?? 1),
            static::humanTime($entry->createdAt),
        ];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function batchHeaders(): array
    {
        return ['UUID', 'Exception', 'Location'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function batchRow(EntryResult $entry, int $index, array $flags = [], bool $full = false): array
    {
        $content = $entry->content;
        $exception = static::classBasename((string)($content['class'] ?? ''))
            . ': ' . ($content['message'] ?? '');

        return [
            static::shortUuid((string)$entry->id),
            $full ? $exception : static::limit($exception, 40),
            ($content['file'] ?? '') . ':' . ($content['line'] ?? ''),
        ];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function detailFields(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;
        $blocks = ['Message' => (string)($content['message'] ?? '')];

        if (!empty($content['line_preview'])) {
            $blocks['Code Context'] = static::codeContext(
                $content['line_preview'],
                $content['line'] ?? 0,
            );
        }

        return [
            'label' => 'Exception',
            'subtitle' => static::classBasename((string)($content['class'] ?? '')),
            'fields' => [
                'Class' => (string)($content['class'] ?? ''),
                'File' => ($content['file'] ?? '') . ':' . ($content['line'] ?? ''),
                'Occurrences' => (string)($content['occurrences'] ?? 1),
                'Resolved' => (string)($content['resolved_at'] ?? 'No'),
            ],
            'list' => static::traceList($content['trace'] ?? null, 15),
            'blocks' => $blocks,
        ];
    }
}
