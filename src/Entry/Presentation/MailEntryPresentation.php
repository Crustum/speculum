<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * Mail entry presentation.
 */
class MailEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'Outgoing mail with recipients and body.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $content = $entry->content;
        $text = (string)($content['subject'] ?? $content['mailable'] ?? 'mail');

        return $full ? $text : static::limit($text, 80);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Mailable', 'Subject', 'To', 'Created'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableRow(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;
        $subject = (string)($content['subject'] ?? '');

        return [
            static::shortUuid((string)$entry->id),
            static::classBasename((string)($content['mailable'] ?? '')),
            $full ? $subject : static::limit($subject, 40),
            $full
                ? static::addresses($content['to'] ?? null)
                : static::addresses($content['to'] ?? null, 30),
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
            'label' => 'Mail',
            'subtitle' => (string)($content['subject'] ?? ''),
            'fields' => [
                'Mailable' => (string)($content['mailable'] ?? ''),
                'Subject' => (string)($content['subject'] ?? ''),
                'Queued' => empty($content['queued']) ? '' : 'Yes',
                'To' => static::addresses($content['to'] ?? null),
                'From' => static::addresses($content['from'] ?? null),
            ],
            'list' => null,
            'blocks' => [],
        ];
    }

    /**
     * Format a recipient map (`email => name`) or list as a comma-separated string.
     *
     * @param mixed $value Recipients.
     * @param int|null $length Maximum length (null for full).
     * @return string
     */
    protected static function addresses(mixed $value, ?int $length = null): string
    {
        if (!is_array($value) || $value === []) {
            return '';
        }

        $parts = [];
        foreach ($value as $email => $name) {
            if (is_int($email)) {
                $parts[] = (string)$name;
                continue;
            }

            $parts[] = $name !== null && $name !== ''
                ? $name . ' <' . $email . '>'
                : $email;
        }

        $joined = implode(', ', $parts);

        return $length === null ? $joined : static::limit($joined, $length);
    }
}
