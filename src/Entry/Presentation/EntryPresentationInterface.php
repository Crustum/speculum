<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;

/**
 * Per-type entry presentation contract for human and agent consumers.
 *
 * Table row strings may contain Cake console tags (`<info>`, `<error>`, …):
 * they feed the table renderer only. JSON output always serializes raw entries.
 */
interface EntryPresentationInterface
{
    /**
     * Build a one-line summary for index rows and related listings.
     *
     * The `$full` flag disables truncation for `show` output; `list` rows
     * always truncate.
     *
     * @param \Crustum\Speculum\Entry\EntryResult $entry Entry result.
     * @param bool $full Whether to skip truncation.
     * @return string
     */
    public function summarize(EntryResult $entry, bool $full = false): string;

    /**
     * Table column headers for `list` output.
     *
     * @return list<string>
     */
    public function tableHeaders(): array;

    /**
     * Table row cells for `list` output (console tags allowed).
     *
     * The `$full` flag is accepted for batch reuse; `list` rows always truncate.
     *
     * @param \Crustum\Speculum\Entry\EntryResult $entry Entry result.
     * @param bool $full Whether to skip truncation.
     * @return list<string>
     */
    public function tableRow(EntryResult $entry, bool $full = false): array;

    /**
     * Table column headers for `show` batch sections.
     *
     * Defaults to `tableHeaders()`; types with a dedicated batch shape
     * (queries, exceptions, cache, logs) override this.
     *
     * @return list<string>
     */
    public function batchHeaders(): array;

    /**
     * Table row cells for `show` batch sections (console tags allowed).
     *
     * `$index` is the 1-based position within the section; `$flags` carries
     * section badges such as query `DUP`/`SLOW` markers.
     *
     * @param \Crustum\Speculum\Entry\EntryResult $entry Entry result.
     * @param int $index One-based row position.
     * @param list<string> $flags Row badges.
     * @param bool $full Whether to skip truncation.
     * @return list<string>
     */
    public function batchRow(EntryResult $entry, int $index, array $flags = [], bool $full = false): array;

    /**
     * Detail sections for `show` output (raw values; truncation applied by renderer).
     *
     * @param \Crustum\Speculum\Entry\EntryResult $entry Entry result.
     * @param bool $full Whether to skip truncation.
     * @return array{label: string, subtitle: string, fields: array<string,string>, list: array{label: string, items: list<string>, more: int, moreLabel: string}|null, blocks: array<string,string>}
     */
    public function detailFields(EntryResult $entry, bool $full = false): array;
}
