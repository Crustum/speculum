<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * Job entry presentation.
 */
class JobEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'Queue jobs with status and payload.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $content = $entry->content;

        return static::classBasename((string)($content['name'] ?? ''))
            . ' [' . ($content['status'] ?? '') . ']';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Name', 'Queue', 'Status', 'Created'];
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
            static::classBasename((string)($content['name'] ?? '')),
            (string)($content['queue'] ?? ''),
            static::colorJobStatus((string)($content['status'] ?? '')),
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
        $blocks = ['Data' => static::blockText($content['data'] ?? null)];

        $exception = is_array($content['exception'] ?? null) ? $content['exception'] : [];
        $list = null;
        if ($exception !== []) {
            $message = (string)($exception['message'] ?? '');
            if ($message !== '') {
                $blocks['Exception'] = $message;
            }

            $list = static::traceList($exception['trace'] ?? null, 10);
        }

        return [
            'label' => 'Job',
            'subtitle' => static::classBasename((string)($content['name'] ?? ''))
                . ' [' . ($content['status'] ?? 'pending') . ']',
            'fields' => [
                'Status' => static::colorJobStatus((string)($content['status'] ?? 'pending')),
                'Queue' => (string)($content['queue'] ?? ''),
                'Connection' => (string)($content['connection'] ?? ''),
                'Tries' => (string)($content['tries'] ?? ''),
                'Timeout' => static::unit($content['timeout'] ?? null, 's'),
            ],
            'list' => $list,
            'blocks' => $blocks,
        ];
    }

    /**
     * Colorize a job status for console output.
     *
     * @param string $status Job status.
     * @return string
     */
    protected static function colorJobStatus(string $status): string
    {
        return match ($status) {
            'processed' => "<success>{$status}</success>",
            'failed' => "<error>{$status}</error>",
            'pending' => "<warning>{$status}</warning>",
            default => $status,
        };
    }
}
