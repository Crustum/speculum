<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Cake\Utility\Text;
use Crustum\Speculum\Entry\EntryResult;
use DateTimeInterface;

/**
 * Fallback entry presentation rendering any type generically.
 *
 * Dedicated presentations extend this base for shared helpers and override arms.
 */
class GenericEntryPresentation implements EntryPresentationInterface
{
    /**
     * One-line type description for the `--types` listing.
     *
     * Dedicated presentations override this; the generic fallback has none.
     * Kept off the interface so third-party presentations never break.
     *
     * @return string
     */
    public function describe(): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        return $full
            ? static::jsonText($entry->content)
            : static::limit(static::jsonText($entry->content), 80);
    }

    /**
     * @inheritDoc
     */
    public function tableHeaders(): array
    {
        return ['UUID', 'Type', 'Summary', 'Created'];
    }

    /**
     * @inheritDoc
     */
    public function tableRow(EntryResult $entry, bool $full = false): array
    {
        return [
            static::shortUuid((string)$entry->id),
            $entry->type,
            $this->summarize($entry, $full),
            static::humanTime($entry->createdAt),
        ];
    }

    /**
     * @inheritDoc
     */
    public function batchHeaders(): array
    {
        return $this->tableHeaders();
    }

    /**
     * @inheritDoc
     */
    public function batchRow(EntryResult $entry, int $index, array $flags = [], bool $full = false): array
    {
        return $this->tableRow($entry, $full);
    }

    /**
     * @inheritDoc
     */
    public function detailFields(EntryResult $entry, bool $full = false): array
    {
        return [
            'label' => ucfirst($entry->type),
            'subtitle' => '',
            'fields' => [],
            'list' => null,
            'blocks' => ['Content' => static::blockText($entry->content)],
        ];
    }

    /**
     * Shorten a UUID for display.
     *
     * @param string $uuid Entry UUID.
     * @return string
     */
    protected static function shortUuid(string $uuid): string
    {
        return substr($uuid, 0, 8);
    }

    /**
     * Append a unit to a scalar value, or return an empty string.
     *
     * @param mixed $value Value.
     * @param string $unit Unit suffix.
     * @return string
     */
    protected static function unit(mixed $value, string $unit): string
    {
        if ($value === null || $value === '' || !is_scalar($value)) {
            return '';
        }

        return $value . $unit;
    }

    /**
     * Truncate a string value, returning an empty string for null.
     *
     * @param mixed $value Value.
     * @param int $length Maximum length.
     * @return string
     */
    protected static function limit(mixed $value, int $length): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Text::truncate((string)$value, $length);
    }

    /**
     * Return the class basename without namespace.
     *
     * @param string $class Fully qualified class name.
     * @return string
     */
    protected static function classBasename(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    /**
     * Format a timestamp as a human readable difference.
     *
     * @param \DateTimeInterface $date Timestamp.
     * @return string
     */
    protected static function humanTime(DateTimeInterface $date): string
    {
        if (method_exists($date, 'diffForHumans')) {
            return (string)$date->diffForHumans();
        }

        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Encode a value as compact JSON text.
     *
     * @param mixed $value Value.
     * @return string
     */
    protected static function jsonText(mixed $value): string
    {
        $encoded = json_encode($value);

        return $encoded === false ? '' : $encoded;
    }

    /**
     * Render a block value as text (strings pass through, anything else as pretty JSON).
     *
     * @param mixed $value Block value.
     * @return string
     */
    protected static function blockText(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? '' : $encoded;
    }

    /**
     * Format a PHP stack trace as listing lines with an overflow count.
     *
     * @param mixed $trace Trace frames.
     * @param int $limit Maximum frames shown.
     * @return array{label: string, items: list<string>, more: int, moreLabel: string}|null
     */
    protected static function traceList(mixed $trace, int $limit): ?array
    {
        if (!is_array($trace) || $trace === []) {
            return null;
        }

        $items = [];
        foreach (array_slice(array_values($trace), 0, $limit) as $frame) {
            if (!is_array($frame)) {
                continue;
            }

            $items[] = ($frame['file'] ?? '?') . ':' . ($frame['line'] ?? '?');
        }

        if ($items === []) {
            return null;
        }

        return [
            'label' => 'Stack Trace',
            'items' => $items,
            'more' => max(0, count($trace) - $limit),
            'moreLabel' => 'frames',
        ];
    }

    /**
     * Format a line preview map as plain text with a marker on the given line.
     *
     * @param mixed $preview Line number to code map.
     * @param mixed $line Highlighted line number.
     * @return string
     */
    protected static function codeContext(mixed $preview, mixed $line): string
    {
        if (!is_array($preview)) {
            return '';
        }

        $lines = [];
        foreach ($preview as $lineNo => $code) {
            $marker = (int)$lineNo === (int)$line ? ' >' : '  ';
            $lines[] = $lineNo . $marker . ' ' . $code;
        }

        return implode("\n", $lines);
    }
}
