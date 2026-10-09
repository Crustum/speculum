<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * Log entry presentation.
 */
class LogEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'Application log records.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $content = $entry->content;
        $message = (string)($content['message'] ?? '');

        return '[' . ($content['level'] ?? '') . '] '
            . ($full ? $message : static::limit($message, 60));
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Level', 'Message', 'Created'];
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
            static::colorLevel((string)($content['level'] ?? '')),
            $full
                ? (string)($content['message'] ?? '')
                : static::limit($content['message'] ?? null, 60),
            static::humanTime($entry->createdAt),
        ];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function batchHeaders(): array
    {
        return ['Level', 'Message'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function batchRow(EntryResult $entry, int $index, array $flags = [], bool $full = false): array
    {
        $content = $entry->content;

        return [
            (string)($content['level'] ?? ''),
            $full
                ? (string)($content['message'] ?? '')
                : static::limit($content['message'] ?? null, 60),
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
            'label' => 'Log',
            'subtitle' => (string)($content['level'] ?? ''),
            'fields' => [
                'Message' => (string)($content['message'] ?? ''),
            ],
            'list' => null,
            'blocks' => [
                'Context' => static::blockText($content['context'] ?? null),
            ],
        ];
    }

    /**
     * Colorize a log level for console output.
     *
     * @param string $level Log level.
     * @return string
     */
    protected static function colorLevel(string $level): string
    {
        return match ($level) {
            'emergency', 'alert', 'critical', 'error' => "<error>{$level}</error>",
            'warning' => "<warning>{$level}</warning>",
            'notice', 'info' => "<info>{$level}</info>",
            'debug' => "<comment>{$level}</comment>",
            default => $level,
        };
    }
}
