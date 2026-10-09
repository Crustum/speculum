<?php
declare(strict_types=1);

namespace Crustum\Speculum\Command\Trait;

use Cake\Console\ConsoleIo;
use Cake\Utility\Text;
use DateTimeInterface;

/**
 * Console rendering mechanics for inspection commands.
 *
 * Tables, detail sections, batch groups, and JSON blocks. No entry-type
 * knowledge lives here: per-type shapes come from the presentation registry
 * via the inspection service.
 */
trait ConsoleFormattingTrait
{
    /**
     * Render a table (headers are the first row for the table helper).
     *
     * @param \Cake\Console\ConsoleIo $io Console I/O.
     * @param list<string> $headers Column headers.
     * @param list<list<string>> $rows Table rows.
     * @return void
     */
    protected function renderTable(ConsoleIo $io, array $headers, array $rows): void
    {
        $helper = $io->helper('table');
        $helper->setConfig('headers', true);
        $helper->output([$headers, ...$rows]);
    }

    /**
     * Render a table without headers (single- or two-column listings).
     *
     * @param \Cake\Console\ConsoleIo $io Console I/O.
     * @param list<list<string>> $rows Table rows.
     * @return void
     */
    protected function renderPlainTable(ConsoleIo $io, array $rows): void
    {
        $helper = $io->helper('table');
        $helper->setConfig('headers', false);
        $helper->output($rows);
    }

    /**
     * Render an entry detail payload (label, fields, list, blocks).
     *
     * @param \Cake\Console\ConsoleIo $io Console I/O.
     * @param array{label: string, subtitle: string, fields: array<string,string>, list: array{label: string, items: list<string>, more: int, moreLabel: string}|null, blocks: array<string,string>} $detail Detail payload.
     * @param \DateTimeInterface $createdAt Entry creation timestamp.
     * @param string $hostname Entry hostname.
     * @param bool $full Whether to skip truncation.
     * @return void
     */
    protected function renderDetail(
        ConsoleIo $io,
        array $detail,
        DateTimeInterface $createdAt,
        string $hostname,
        bool $full,
    ): void {
        $title = $detail['subtitle'] !== '' ? $detail['label'] . ': ' . $detail['subtitle'] : $detail['label'];
        $io->info($title);

        $rows = [
            ['Time', $this->formatDetailTime($createdAt)],
        ];
        if ($hostname !== '') {
            $rows[] = ['Hostname', $hostname];
        }

        foreach ($detail['fields'] as $label => $value) {
            if ($value === '') {
                continue;
            }

            $rows[] = [$label, $value];
        }

        $this->renderPlainTable($io, $rows);

        if ($detail['list'] !== null) {
            $list = $detail['list'];
            $io->info($list['label']);
            $this->renderPlainTable(
                $io,
                array_map(static fn(string $item): array => [$item], $list['items']),
            );

            if ($list['more'] > 0 && $list['moreLabel'] !== '') {
                $io->out('... and ' . $list['more'] . ' more ' . $list['moreLabel']);
            }
        }

        foreach ($detail['blocks'] as $label => $content) {
            if ($content === '') {
                continue;
            }

            $io->info($label);
            $io->out($full ? $content : Text::truncate($content, 1000));
        }
    }

    /**
     * Format an entry timestamp as human difference with absolute time.
     *
     * @param \DateTimeInterface $date Timestamp.
     * @return string
     */
    protected function formatDetailTime(DateTimeInterface $date): string
    {
        $absolute = $date->format('Y-m-d H:i:s');
        if (method_exists($date, 'diffForHumans')) {
            return $date->diffForHumans() . ' (' . $absolute . ')';
        }

        return $absolute;
    }

    /**
     * Render batch context groups (queries, exceptions, cache, logs, others).
     *
     * @param \Cake\Console\ConsoleIo $io Console I/O.
     * @param array{queries: array{headers: list<string>, rows: list<list<string>>, stats: array{total: int, time: float, slow: int, duplicateGroups: int}, more: int}, exceptions: array{headers: list<string>, rows: list<list<string>>, total: int, more: int}, cache: array{stats: array{hits: int, misses: int, total: int, rate: float|null}, headers: list<string>, rows: list<list<string>>, total: int, more: int}, logs: array{headers: list<string>, rows: list<list<string>>, total: int, more: int}, others: list<array{label: string, count: int, items: list<array{id: string, summary: string}>, more: int}>} $groups Batch groups.
     * @param list<string> $requestedTypes Requested batch types.
     * @param string|null $batchId Batch UUID.
     * @return void
     */
    protected function renderBatchGroups(ConsoleIo $io, array $groups, array $requestedTypes, ?string $batchId): void
    {
        if (
            $groups['queries']['rows'] === []
            && $groups['exceptions']['rows'] === []
            && $groups['cache']['rows'] === []
            && $groups['logs']['rows'] === []
            && $groups['others'] === []
        ) {
            if ($requestedTypes !== []) {
                $io->out('No batch entries of type ' . implode(', ', $requestedTypes) . '.');
            }

            return;
        }

        $io->info('Related Entries - batch ' . substr((string)$batchId, 0, 8));

        if ($groups['queries']['rows'] !== []) {
            $stats = $groups['queries']['stats'];
            $title = 'Queries - ' . $stats['total'] . ' total, ' . $stats['time'] . 'ms';
            if ($stats['slow'] > 0) {
                $title .= ', ' . $stats['slow'] . ' slow';
            }

            if ($stats['duplicateGroups'] > 0) {
                $title .= ', ' . $stats['duplicateGroups']
                    . ' duplicate group' . ($stats['duplicateGroups'] === 1 ? '' : 's');
            }

            $io->info($title);
            $this->renderTable($io, $groups['queries']['headers'], $groups['queries']['rows']);
            $this->renderMore($io, $stats['total'], 20, 'queries');
        }

        if ($groups['exceptions']['rows'] !== []) {
            $io->info('Exceptions - ' . $groups['exceptions']['total']);
            $this->renderTable($io, $groups['exceptions']['headers'], $groups['exceptions']['rows']);
            $this->renderMore($io, $groups['exceptions']['total'], 10, 'exceptions');
        }

        if ($groups['cache']['rows'] !== []) {
            $stats = $groups['cache']['stats'];
            $title = 'Cache - ' . $stats['hits'] . ' hits, ' . $stats['misses'] . ' misses';
            if ($stats['rate'] !== null) {
                $title .= ' - ' . round($stats['rate'] * 100, 1) . '% hit rate';
            }

            $io->info($title);
            $this->renderTable($io, $groups['cache']['headers'], $groups['cache']['rows']);
            $this->renderMore($io, $groups['cache']['total'], 10, 'cache entries');
        }

        if ($groups['logs']['rows'] !== []) {
            $io->info('Logs - ' . $groups['logs']['total']);
            $this->renderTable($io, $groups['logs']['headers'], $groups['logs']['rows']);
            $this->renderMore($io, $groups['logs']['total'], 10, 'logs');
        }

        foreach ($groups['others'] as $other) {
            $io->info($other['label'] . ' (' . $other['count'] . ')');
            $this->renderPlainTable(
                $io,
                array_map(
                    static fn(array $item): array => [$item['id'], $item['summary']],
                    $other['items'],
                ),
            );
            $this->renderMore($io, $other['count'], 5, $other['label']);
        }
    }

    /**
     * Render a `... and N more {label}` overflow line when capped.
     *
     * @param \Cake\Console\ConsoleIo $io Console I/O.
     * @param int $total Total items.
     * @param int $limit Shown items.
     * @param string $label Item label.
     * @return void
     */
    protected function renderMore(ConsoleIo $io, int $total, int $limit, string $label): void
    {
        if ($total > $limit) {
            $io->out('... and ' . ($total - $limit) . ' more ' . $label);
        }
    }

    /**
     * Format a value as a pretty-printed JSON block.
     *
     * @param mixed $data Data.
     * @return string
     */
    protected function jsonBlock(mixed $data): string
    {
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? '' : $encoded;
    }
}
