<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * Authorization entry presentation (also serves `cakedc_auth` entries).
 */
class AuthorizationEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'Authorization checks with allow/deny results.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $content = $entry->content;
        $summary = ($content['ability'] ?? '')
            . ' (' . (empty($content['allowed']) ? 'Denied' : 'Allowed') . ')';

        return $full ? $summary : static::limit($summary, 80);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Ability', 'Result', 'Role', 'Policy', 'Created'];
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
            $full ? (string)($content['ability'] ?? '') : static::limit($content['ability'] ?? null, 50),
            empty($content['allowed']) ? '<error>Denied</error>' : '<info>Allowed</info>',
            $full ? (string)($content['role'] ?? '') : static::limit($content['role'] ?? null, 20),
            $full ? (string)($content['policy'] ?? '') : static::limit($content['policy'] ?? null, 30),
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
            'label' => 'Authorization',
            'subtitle' => '',
            'fields' => [
                'Ability' => (string)($content['ability'] ?? ''),
                'Result' => empty($content['allowed']) ? 'Denied' : 'Allowed',
                'Kind' => (string)($content['kind'] ?? ''),
                'Role' => (string)($content['role'] ?? ''),
                'Policy' => (string)($content['policy'] ?? ''),
                'User' => isset($content['user_id']) ? (string)$content['user_id'] : '',
                'Reason' => (string)($content['reason'] ?? ''),
            ],
            'list' => null,
            'blocks' => [
                'Checked Context' => static::blockText($content['checked'] ?? null),
                'Matched Rule' => static::blockText($content['permission'] ?? null),
                'Route Params' => static::blockText($content['params'] ?? null),
            ],
        ];
    }
}
