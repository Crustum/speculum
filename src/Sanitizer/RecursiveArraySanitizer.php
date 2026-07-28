<?php
declare(strict_types=1);

namespace Crustum\Speculum\Sanitizer;

/**
 * Recursively redacts array values whose keys match sensitive patterns.
 */
class RecursiveArraySanitizer extends AbstractPatternSanitizer
{
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
}
