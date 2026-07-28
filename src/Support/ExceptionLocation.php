<?php
declare(strict_types=1);

namespace Crustum\Speculum\Support;

use Throwable;

/**
 * Resolve a user-facing exception file/line, preferring app code over vendor.
 */
class ExceptionLocation
{
    /**
     * File and line to show for an exception.
     *
     * Uses the throw site when it is outside `vendor/`. Otherwise walks the
     * stack for the first non-vendor frame. Falls back to the throw site when
     * every frame is vendor (or has no file).
     *
     * @param \Throwable $exception Exception instance.
     * @return array{file: string, line: int}
     */
    public static function fromThrowable(Throwable $exception): array
    {
        return static::resolve(
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTrace(),
        );
    }

    /**
     * Resolve a display location from throw site and stack frames.
     *
     * @param string $file Throw-site file.
     * @param int $line Throw-site line.
     * @param list<array<string, mixed>> $trace Stack frames (`file` / `line`).
     * @return array{file: string, line: int}
     */
    public static function resolve(string $file, int $line, array $trace): array
    {
        if (str_contains($file, "eval()'d code")) {
            return [
                'file' => $file,
                'line' => $line,
            ];
        }

        if ($file !== '' && !static::isVendorPath($file)) {
            return [
                'file' => $file,
                'line' => $line,
            ];
        }

        foreach ($trace as $frame) {
            $frameFile = $frame['file'] ?? null;
            if (!is_string($frameFile)) {
                continue;
            }

            if ($frameFile === '') {
                continue;
            }

            if (static::isVendorPath($frameFile)) {
                continue;
            }

            $frameLine = $frame['line'] ?? 0;

            return [
                'file' => $frameFile,
                'line' => is_int($frameLine) ? $frameLine : 0,
            ];
        }

        return [
            'file' => $file,
            'line' => $line,
        ];
    }

    /**
     * Whether a path is under a Composer `vendor` directory.
     *
     * @param string $file Absolute or relative path.
     * @return bool
     */
    public static function isVendorPath(string $file): bool
    {
        $normalized = str_replace('\\', '/', $file);

        return str_contains($normalized, '/vendor/');
    }
}
