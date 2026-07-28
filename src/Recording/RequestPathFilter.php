<?php
declare(strict_types=1);

namespace Crustum\Speculum\Recording;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;

/**
 * Filters HTTP paths and CLI commands that should not start Speculum recording.
 */
final class RequestPathFilter
{
    /**
     * Determine whether the request path should be ignored by Speculum.
     *
     * @param \Cake\Http\ServerRequest $request Request.
     * @return bool
     */
    public static function shouldIgnoreRequest(ServerRequest $request): bool
    {
        $path = trim($request->getPath(), '/');
        $speculumPath = trim((string)Configure::read('Speculum.path', 'speculum'), '/');

        $only = Configure::read('Speculum.only_paths', []);
        if ($only !== []) {
            foreach ($only as $pattern) {
                if (fnmatch($pattern, $path) || fnmatch(ltrim((string)$pattern, '/'), $path)) {
                    return false;
                }
            }

            return true;
        }

        $configured = Configure::read('Speculum.ignore_paths', []);
        if (!is_array($configured)) {
            $configured = [];
        }

        $ignored = array_merge([
            $speculumPath . '*',
            'speculum/api*',
        ], $configured);

        foreach ($ignored as $pattern) {
            $normalized = ltrim((string)$pattern, '/');
            if ($normalized === '') {
                continue;
            }

            if (fnmatch($normalized, $path) || fnmatch($normalized, $path . '/*')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the current CLI command is allowed to record.
     *
     * @return bool
     */
    public static function runningApprovedConsoleCommand(): bool
    {
        $command = self::currentConsoleCommand();
        if ($command === '') {
            return true;
        }

        $ignored = Configure::read('Speculum.ignoreCommands', Configure::read('Speculum.ignore_commands', []));
        if (!is_array($ignored)) {
            $ignored = [];
        }

        $ignored = array_values(array_filter($ignored, is_string(...)));

        $prefix = explode(' ', $command, 2)[0];

        return !in_array($command, $ignored, true) && !in_array($prefix, $ignored, true);
    }

    /**
     * Resolve the current Cake console command name from argv.
     *
     * @return string Space-separated command name (for example `migrations migrate`).
     */
    public static function currentConsoleCommand(): string
    {
        $parts = [];
        foreach (array_slice($_SERVER['argv'] ?? [], 1) as $arg) {
            if (!is_string($arg)) {
                continue;
            }

            if ($arg === '') {
                continue;
            }

            if (str_starts_with($arg, '-')) {
                break;
            }

            $parts[] = $arg;
        }

        return implode(' ', $parts);
    }
}
