<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * HTTP client entry presentation.
 */
class HttpClientEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'Outgoing HTTP client calls.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $content = $entry->content;
        $uri = (string)($content['uri'] ?? '');

        return trim(
            ($content['method'] ?? '') . ' '
            . ($full ? $uri : static::limit($uri, 40)) . ' -> '
            . (isset($content['response_status']) ? (string)$content['response_status'] : 'N/A'),
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableHeaders(): array
    {
        return ['UUID', 'Method', 'URI', 'Status', 'Duration', 'Created'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tableRow(EntryResult $entry, bool $full = false): array
    {
        $content = $entry->content;
        $uri = (string)($content['uri'] ?? '');

        return [
            static::shortUuid((string)$entry->id),
            (string)($content['method'] ?? ''),
            $full ? $uri : static::limit($uri, 40),
            isset($content['response_status']) ? (string)$content['response_status'] : 'N/A',
            static::unit($content['duration'] ?? null, 'ms'),
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
        $subtitle = trim(
            ($content['method'] ?? '') . ' ' . ($content['uri'] ?? ''),
        );

        return [
            'label' => 'Client Request',
            'subtitle' => $subtitle,
            'fields' => [
                'Status' => isset($content['response_status'])
                    ? (string)$content['response_status']
                    : 'N/A',
                'Duration' => static::unit($content['duration'] ?? null, 'ms'),
            ],
            'list' => null,
            'blocks' => [
                'Payload' => static::blockText($content['payload'] ?? null),
                'Response' => static::blockText($content['response'] ?? null),
            ],
        ];
    }
}
