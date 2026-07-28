<?php
declare(strict_types=1);

namespace Crustum\Speculum\Mcp;

use Crustum\Speculum\Entry\EntryResult;
use Crustum\Speculum\Enum\EntryType;
use DateTimeInterface;

/**
 * Formats Speculum entries for MCP tool responses.
 */
final class EntryPresenter
{
    public const DEFAULT_LIMIT = 15;

    public const MAX_LIMIT = 50;

    public const CONTENT_TRUNCATE = 1200;

    public const LABEL_TRUNCATE = 160;

    /**
     * Build a short summary row for search results.
     *
     * @param \Crustum\Speculum\Entry\EntryResult $entry Entry result.
     * @return array{
     *     id: mixed,
     *     sequence: mixed,
     *     batch_id: string,
     *     type: string,
     *     family_hash: string|null,
     *     label: string,
     *     tags: list<string>,
     *     created: string
     * }
     */
    public static function summarize(EntryResult $entry): array
    {
        return [
            'id' => $entry->id,
            'sequence' => $entry->sequence,
            'batch_id' => $entry->batchId,
            'type' => $entry->type,
            'family_hash' => $entry->familyHash,
            'label' => self::label($entry),
            'tags' => $entry->jsonSerialize()['tags'],
            'created' => $entry->createdAt->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * Build a truncated entry payload for detail responses.
     *
     * @param \Crustum\Speculum\Entry\EntryResult $entry Entry result.
     * @return array<string, mixed>
     */
    public static function detail(EntryResult $entry): array
    {
        $payload = $entry->jsonSerialize();
        $payload['content'] = self::truncateValue($payload['content'], self::CONTENT_TRUNCATE);

        return $payload;
    }

    /**
     * Clamp a requested limit into the allowed MCP range.
     *
     * @param mixed $limit Requested limit.
     * @return int
     */
    public static function clampLimit(mixed $limit): int
    {
        if (!is_numeric($limit)) {
            return self::DEFAULT_LIMIT;
        }

        $value = (int)$limit;
        if ($value < 1) {
            return self::DEFAULT_LIMIT;
        }

        return min($value, self::MAX_LIMIT);
    }

    /**
     * Derive a short human-readable label from entry content.
     *
     * @param \Crustum\Speculum\Entry\EntryResult $entry Entry result.
     * @return string
     */
    public static function label(EntryResult $entry): string
    {
        $content = $entry->content;
        $label = match ($entry->type) {
            EntryType::Batch->value => (string)($content['name'] ?? $content['id'] ?? 'batch'),
            EntryType::Broadcast->value => (string)($content['channel'] ?? $content['event'] ?? 'broadcast'),
            EntryType::Cache->value => (string)($content['key'] ?? $content['type'] ?? 'cache'),
            EntryType::Command->value => (string)($content['command'] ?? 'command'),
            EntryType::Event->value => (string)($content['name'] ?? 'event'),
            EntryType::Exception->value => (string)($content['class'] ?? $content['message'] ?? 'exception'),
            EntryType::HttpClient->value => trim(
                ($content['method'] ?? '') . ' ' . ($content['uri'] ?? ''),
            ),
            EntryType::Job->value => (string)($content['name'] ?? $content['status'] ?? 'job'),
            EntryType::Log->value => (string)($content['message'] ?? $content['level'] ?? 'log'),
            EntryType::Mail->value => (string)($content['mailable'] ?? $content['subject'] ?? 'mail'),
            EntryType::Model->value => (string)($content['model'] ?? $content['action'] ?? 'model'),
            EntryType::Notification->value => (string)($content['notification'] ?? 'notification'),
            EntryType::Query->value => (string)($content['sql'] ?? 'query'),
            EntryType::Request->value => trim(
                ($content['method'] ?? '') . ' ' . ($content['uri'] ?? $content['path'] ?? ''),
            ),
            EntryType::ScheduledTask->value => (string)($content['description'] ?? $content['command'] ?? 'schedule'),
            EntryType::View->value => (string)($content['name'] ?? $content['path'] ?? 'view'),
            EntryType::VarDump->value => trim(
                ($content['summary'] ?? $content['entry_point_description'] ?? 'vardump')
                . (
                    empty($content['file'])
                        ? ''
                        : ' @ ' . $content['file'] . (isset($content['line']) ? ':' . $content['line'] : '')
                ),
            ),
            default => $entry->type,
        };

        $label = preg_replace('/\s+/', ' ', trim($label)) ?? $label;

        return self::truncateString($label, self::LABEL_TRUNCATE);
    }

    /**
     * Truncate nested values for agent-safe payloads.
     *
     * @param mixed $value Value to truncate.
     * @param int $remaining Remaining character budget.
     * @return mixed
     */
    public static function truncateValue(mixed $value, int $remaining): mixed
    {
        if ($remaining <= 0) {
            return '[truncated]';
        }

        if (is_string($value)) {
            return self::truncateString($value, $remaining);
        }

        if (!is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $key => $item) {
            if ($remaining <= 0) {
                $out['…'] = '[truncated]';
                break;
            }

            $encodedKey = is_string($key) ? $key : (string)$key;
            $budget = max(24, (int)floor($remaining / max(1, count($value))));
            $out[$key] = self::truncateValue($item, $budget);
            $remaining -= strlen($encodedKey) + 8;
        }

        return $out;
    }

    /**
     * Truncate a string with an ellipsis marker.
     *
     * @param string $value Input string.
     * @param int $maxLength Maximum length.
     * @return string
     */
    public static function truncateString(string $value, int $maxLength): string
    {
        if ($maxLength < 1 || strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, max(0, $maxLength - 1)) . '…';
    }
}
