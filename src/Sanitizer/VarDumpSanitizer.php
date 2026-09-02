<?php
declare(strict_types=1);

namespace Crustum\Speculum\Sanitizer;

use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\VarDumper\Cloner\VarCloner;

/**
 * Redacts sensitive keys inside Symfony VarDumper clones (entities, arrays, props).
 */
class VarDumpSanitizer
{
    /**
     * Register casters that redact sensitive properties before HTML dump.
     *
     * @param \Symfony\Component\VarDumper\Cloner\VarCloner $cloner Cloner.
     * @return void
     */
    public static function configureCloner(VarCloner $cloner): void
    {
        $cloner->addCasters([
            '*' => static::castObject(...),
        ]);
    }

    /**
     * Sanitize a top-level array before cloning.
     *
     * @param mixed $value Value to prepare.
     * @return mixed
     */
    public static function prepare(mixed $value): mixed
    {
        if (is_array($value)) {
            return SensitiveData::varDumpAttributes($value);
        }

        return $value;
    }

    /**
     * Redact matching property names and nested array keys on any object.
     *
     * @param object $object Dumped object.
     * @param array<array-key, mixed> $array Cast property map.
     * @param \Symfony\Component\VarDumper\Cloner\Stub $stub Stub.
     * @param bool $isNested Whether nested.
     * @return array<array-key, mixed>
     */
    public static function castObject(
        object $object,
        array $array,
        Stub $stub,
        bool $isNested,
    ): array {
        unset($object, $stub, $isNested);

        $patterns = SensitiveData::varDumpPatterns();
        $sanitizer = new RecursiveArraySanitizer($patterns);
        $out = [];

        foreach ($array as $key => $value) {
            $leaf = static::propertyLeafName((string)$key);
            if ($leaf !== '' && static::matchesLeaf($leaf, $patterns)) {
                $out[$key] = AbstractPatternSanitizer::DEFAULT_REPLACEMENT;
                continue;
            }

            if (is_array($value)) {
                /** @var array<array-key, mixed> $sanitized */
                $sanitized = $sanitizer->sanitize($value);
                $out[$key] = $sanitized;
                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * Strip Symfony caster prefixes from a property key.
     *
     * @param string $key Prefixed or plain property key.
     * @return string
     */
    protected static function propertyLeafName(string $key): string
    {
        if (!str_starts_with($key, "\0")) {
            return $key;
        }

        $parts = explode("\0", $key);

        return $parts[array_key_last($parts)] ?? $key;
    }

    /**
     * Whether a property leaf name matches any sensitive glob pattern.
     *
     * @param string $leaf Property leaf name.
     * @param list<string> $patterns Glob patterns.
     * @return bool
     */
    protected static function matchesLeaf(string $leaf, array $patterns): bool
    {
        return array_any($patterns, fn(string $pattern): bool => $pattern !== '' && fnmatch($pattern, $leaf, FNM_CASEFOLD));
    }
}
