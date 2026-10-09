<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * Batch entry presentation.
 */
class BatchEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'Queue batches with progress.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $content = $entry->content;
        $summary = ($content['name'] ?? $content['id'] ?? '')
            . ' (' . static::status($content) . ', ' . ($content['progress'] ?? 0) . '%)';

        return $full ? $summary : static::limit($summary, 80);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Batch', 'Status', 'Size', 'Progress', 'Created'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableRow(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;
        $status = static::status($content);

        return [
            static::shortUuid((string)$entry->id),
            $full
                ? (string)($content['name'] ?? $content['id'] ?? '')
                : static::limit($content['name'] ?? $content['id'] ?? null, 40),
            $status === 'Failures' ? '<error>Failures</error>' : $status,
            isset($content['totalJobs']) ? (string)$content['totalJobs'] : '',
            isset($content['progress']) ? $content['progress'] . '%' : '',
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
            'label' => 'Batch',
            'subtitle' => '',
            'fields' => [
                'Batch' => (string)($content['name'] ?? $content['id'] ?? ''),
                'Status' => static::status($content),
                'Connection' => (string)($content['connection'] ?? ''),
                'Queue' => (string)($content['queue'] ?? ''),
                'Size' => isset($content['totalJobs']) ? (string)$content['totalJobs'] : '',
                'Pending' => isset($content['pendingJobs']) ? (string)$content['pendingJobs'] : '',
                'Progress' => isset($content['progress']) ? $content['progress'] . '%' : '',
                'Finished' => (string)($content['finished'] ?? ''),
            ],
            'list' => null,
            'blocks' => [],
        ];
    }

    /**
     * Derive the batch status from progress and failure counts.
     *
     * @param array<string, mixed> $content Entry content.
     * @return string
     */
    protected static function status(array $content): string
    {
        $progress = (int)($content['progress'] ?? 0);
        if ($progress >= 100) {
            return 'Finished';
        }

        if ((int)($content['failedJobs'] ?? 0) > 0) {
            return 'Failures';
        }

        if ((int)($content['totalJobs'] ?? 0) === 0 || (int)($content['pendingJobs'] ?? 0) > 0) {
            return 'Pending';
        }

        return 'Running';
    }
}
