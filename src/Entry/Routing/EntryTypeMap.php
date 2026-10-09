<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Routing;

/**
 * Lean path to entry type map for the Entry layer.
 *
 * Holds only `resource path <-> entry type value(s)` pairs so Entry services
 * (presentation, listing) resolve CLI/API input without depending on the
 * Watching layer. The full resource definition (watcher, soft gates) stays in
 * `WatcherRegistry`, which mirrors registrations here.
 */
final class EntryTypeMap
{
    /**
     * Resource path to entry type value(s).
     *
     * @var array<string, list<string>|string>
     */
    private static array $types = [];

    /**
     * Register a path to type mapping, merging with existing types.
     *
     * @param string $path URL segment under `/speculum/api`.
     * @param list<string>|string $type Entry type value(s).
     * @return void
     */
    public static function register(string $path, string|array $type): void
    {
        $path = trim($path, '/');
        if ($path === '') {
            return;
        }

        $incoming = is_array($type) ? $type : [$type];
        if (!isset(self::$types[$path])) {
            self::$types[$path] = count($incoming) === 1 ? $incoming[0] : $incoming;

            return;
        }

        $existing = self::$types[$path];
        $existing = is_array($existing) ? $existing : [$existing];

        $merged = array_values(array_unique([...$existing, ...$incoming]));
        self::$types[$path] = count($merged) === 1 ? $merged[0] : $merged;
    }

    /**
     * Return the type value(s) for a resource path, or null.
     *
     * @param string $path URL segment.
     * @return list<string>|string|null
     */
    public static function type(string $path): string|array|null
    {
        $path = trim($path, '/');

        return self::$types[$path] ?? null;
    }

    /**
     * Return all registered path to type pairs.
     *
     * @return array<string, list<string>|string>
     */
    public static function all(): array
    {
        return self::$types;
    }

    /**
     * Remove a single path mapping.
     *
     * @param string $path URL segment.
     * @return void
     */
    public static function unregister(string $path): void
    {
        unset(self::$types[trim($path, '/')]);
    }

    /**
     * Clear all mappings (tests / process reset).
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$types = [];
    }
}
