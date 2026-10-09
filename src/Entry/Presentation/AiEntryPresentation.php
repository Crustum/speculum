<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * AI entry presentation.
 */
class AiEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'AI agent and tool runs with usage.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $content = $entry->content;
        $summary = '[' . ($content['category'] ?? '') . '] ' . ($content['name'] ?? '');

        return $full ? $summary : static::limit($summary, 80);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Category', 'Event', 'Provider', 'Duration', 'Created'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableRow(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;
        $provider = (string)($content['provider'] ?? '');
        $model = (string)($content['model'] ?? '');
        if ($provider !== '' && $model !== '') {
            $provider .= ' · ' . $model;
        }

        return [
            static::shortUuid((string)$entry->id),
            (string)($content['category'] ?? ''),
            $full ? (string)($content['name'] ?? '') : static::limit($content['name'] ?? null, 40),
            $full ? $provider : static::limit($provider !== '' ? $provider : null, 30),
            static::unit($content['duration'] ?? null, 'ms')
                . (empty($content['slow']) ? '' : '  <error>SLOW</error>')
                . (empty($content['failed']) ? '' : '  <error>FAILED</error>'),
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
            'label' => 'AI',
            'subtitle' => '',
            'fields' => [
                'Category' => (string)($content['category'] ?? ''),
                'Event' => (string)($content['name'] ?? ''),
                'Invocation ID' => (string)($content['invocationId'] ?? ''),
                'Provider' => (string)($content['provider'] ?? ''),
                'Model' => (string)($content['model'] ?? ''),
                'Step' => isset($content['step']) ? (string)$content['step'] : '',
                'Final Step' => isset($content['is_final']) ? ($content['is_final'] ? 'Yes' : 'No') : '',
                'Duration' => static::unit($content['duration'] ?? null, 'ms')
                    . (empty($content['slow']) ? '' : '  <error>SLOW</error>'),
                'Failed' => empty($content['failed']) ? 'No' : '<error>Yes</error>',
                'Summary' => (string)($content['summary'] ?? ''),
            ],
            'list' => null,
            'blocks' => [
                'Payload' => static::blockText($content['payload'] ?? null),
            ],
        ];
    }
}
