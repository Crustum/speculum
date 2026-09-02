<?php
declare(strict_types=1);

namespace Crustum\Speculum\Sanitizer;

/**
 * Recursively redacts array values whose keys match sensitive patterns.
 */
class RecursiveArraySanitizer extends AbstractPatternSanitizer
{
    /**
     * @param list<string> $excludeKeys Leaf keys (or dotted paths) whose whole
     *     subtree must be left untouched (e.g. AI `usage` token counts that
     *     collide with the `*token*` redaction pattern but are not secrets).
     */
    public function __construct(
        array $patterns = [],
        string $replacement = self::DEFAULT_REPLACEMENT,
        bool $caseInsensitive = true,
        protected array $excludeKeys = [],
    ) {
        parent::__construct($patterns, $replacement, $caseInsensitive);
    }

    /**
     * @inheritDoc
     */
    public function sanitize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        return $this->sanitizeArray($value, '');
    }

    /**
     * Walk an array and redact matching keys.
     *
     * @param array<array-key, mixed> $data Data.
     * @param string $path Dotted parent path.
     * @return array<array-key, mixed>
     */
    protected function sanitizeArray(array $data, string $path): array
    {
        $sanitized = [];
        foreach ($data as $key => $item) {
            $keyString = (string)$key;
            $currentPath = $path === '' ? $keyString : $path . '.' . $keyString;

            if ($this->isExcluded($keyString, $currentPath)) {
                $sanitized[$key] = $item;
                continue;
            }

            if ($this->matches($keyString) || $this->matches($currentPath)) {
                $sanitized[$key] = $this->replacement;
                continue;
            }

            if (is_array($item)) {
                $sanitized[$key] = $this->sanitizeArray($item, $currentPath);
                continue;
            }

            $sanitized[$key] = $item;
        }

        return $sanitized;
    }

    /**
     * Whether the key (or its dotted path) is explicitly excluded from redaction.
     *
     * @param string $key Leaf key.
     * @param string $path Dotted path including the leaf key.
     * @return bool
     */
    protected function isExcluded(string $key, string $path): bool
    {
        if ($this->excludeKeys === []) {
            return false;
        }

        $flags = $this->caseInsensitive ? FNM_CASEFOLD : 0;
        foreach ($this->excludeKeys as $exclude) {
            if ($exclude === '') {
                continue;
            }

            if (fnmatch($exclude, $key, $flags) || fnmatch($exclude, $path, $flags)) {
                return true;
            }
        }

        return false;
    }
}
