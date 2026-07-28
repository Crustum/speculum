<?php
declare(strict_types=1);

namespace Crustum\Speculum\Support;

use Cake\Core\Plugin;
use Throwable;

/**
 * Extract surrounding source lines for exception previews.
 */
class ExceptionContext
{
    /**
     * Return surrounding source context for an exception.
     *
     * @param \Throwable $exception Exception instance.
     * @return array<int, string>
     */
    public static function get(Throwable $exception): array
    {
        return static::getEvalContext($exception) ?? static::getFileContext($exception);
    }

    /**
     * Return context for exceptions raised from eval()'d code.
     *
     * @param \Throwable $exception Exception instance.
     * @return array<int, string>|null
     */
    protected static function getEvalContext(Throwable $exception): ?array
    {
        if (str_contains($exception->getFile(), "eval()'d code")) {
            return [
                $exception->getLine() => "eval()'d code",
            ];
        }

        return null;
    }

    /**
     * Return surrounding file lines for an exception location.
     *
     * Only reads files under the application root (blocks crafted paths like /etc/passwd).
     *
     * @param \Throwable $exception Exception instance.
     * @return array<int, string>
     */
    protected static function getFileContext(Throwable $exception): array
    {
        $location = ExceptionLocation::fromThrowable($exception);
        $file = $location['file'];
        $line = $location['line'];
        if (!static::isAllowedContextPath($file)) {
            return [];
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            return [];
        }

        $lines = explode("\n", $contents);
        $start = max(0, $line - 10);
        $slice = array_slice($lines, $start, 20, true);
        $result = [];
        foreach ($slice as $index => $value) {
            $result[$index + 1] = $value;
        }

        return $result;
    }

    /**
     * Whether a path may be read for exception source context.
     *
     * @param string $file Exception file path.
     * @return bool
     */
    protected static function isAllowedContextPath(string $file): bool
    {
        if ($file === '' || !is_file($file) || !is_readable($file)) {
            return false;
        }

        $realFile = realpath($file);
        if ($realFile === false) {
            return false;
        }

        foreach (static::allowedContextRoots() as $root) {
            $realRoot = realpath($root);
            if ($realRoot === false) {
                continue;
            }

            if (static::pathIsInside($realFile, $realRoot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Project roots allowed for exception context reads.
     *
     * Includes application ROOT/APP and every loaded plugin path so path-repo
     * plugins outside the host ROOT (for example Speculum) still get previews.
     *
     * @return list<string>
     */
    protected static function allowedContextRoots(): array
    {
        $roots = [];
        if (defined('ROOT')) {
            $roots[] = (string)ROOT;
        }

        if (defined('APP')) {
            $roots[] = APP;
        }

        foreach (Plugin::loaded() as $name) {
            try {
                $roots[] = Plugin::path($name);
            } catch (Throwable) {
            }
        }

        return array_values(array_unique(array_filter($roots)));
    }

    /**
     * Whether $path is the same as or nested under $root.
     *
     * @param string $path Absolute real path.
     * @param string $root Absolute real root.
     * @return bool
     */
    protected static function pathIsInside(string $path, string $root): bool
    {
        if (strcasecmp($path, $root) === 0) {
            return true;
        }

        $prefix = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
        if (DIRECTORY_SEPARATOR === '\\') {
            return str_starts_with(strtolower($path), strtolower($prefix));
        }

        return str_starts_with($path, $prefix);
    }
}
