<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Presentation;

use Crustum\Speculum\Entry\EntryResult;
use Override;

/**
 * Request entry presentation.
 */
class RequestEntryPresentation extends GenericEntryPresentation
{
    /**
     * One-line type description for the `--types` listing.
     *
     * @return string
     */
    #[Override]
    public function describe(): string
    {
        return 'HTTP requests with response status and duration.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function summarize(EntryResult $entry, bool $full = false): string
    {
        $content = $entry->content;

        return ($content['method'] ?? '') . ' '
            . ($content['uri'] ?? '') . ' -> '
            . ($content['response_status'] ?? '') . ' ('
            . ($content['duration'] ?? '') . 'ms)';
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
            static::colorMethod((string)($content['method'] ?? '')),
            $full ? $uri : static::limit($uri, 40),
            static::colorStatus((int)($content['response_status'] ?? 0)),
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
            'label' => 'Request',
            'subtitle' => $subtitle,
            'fields' => [
                'Controller' => (string)($content['controller_action'] ?? ''),
                'Route' => (string)($content['matched_route'] ?? ''),
                'Duration' => static::unit($content['duration'] ?? null, 'ms'),
                'Memory' => static::unit($content['memory'] ?? null, 'MB'),
                'IP' => (string)($content['ip_address'] ?? ''),
                'User' => static::formatUser($content['user'] ?? null),
            ],
            'list' => null,
            'blocks' => [
                'Payload' => static::blockText($content['payload'] ?? null),
                'Response' => static::blockText($content['response'] ?? null),
            ],
        ];
    }

    /**
     * Format the authenticated user for display.
     *
     * @param mixed $user User payload.
     * @return string
     */
    protected static function formatUser(mixed $user): string
    {
        if (!is_array($user)) {
            return '';
        }

        $name = (string)($user['name'] ?? '');
        $email = (string)($user['email'] ?? '');
        $label = trim($name . ($name !== '' && $email !== '' ? " ({$email})" : $email));

        if (isset($user['id'])) {
            $label .= " #{$user['id']}";
        }

        return trim($label);
    }

    /**
     * Colorize an HTTP method for console output.
     *
     * @param string $method HTTP method.
     * @return string
     */
    protected static function colorMethod(string $method): string
    {
        return match (strtoupper($method)) {
            'GET' => "<comment>{$method}</comment>",
            'POST', 'PATCH', 'PUT' => "<info>{$method}</info>",
            'DELETE' => "<error>{$method}</error>",
            default => $method,
        };
    }

    /**
     * Colorize an HTTP status code for console output.
     *
     * @param int $status Status code.
     * @return string
     */
    protected static function colorStatus(int $status): string
    {
        return match (true) {
            $status < 300 => "<success>{$status}</success>",
            $status < 400 => "<info>{$status}</info>",
            $status < 500 => "<warning>{$status}</warning>",
            default => "<error>{$status}</error>",
        };
    }
}
