<?php
declare(strict_types=1);

namespace Crustum\Speculum\Sanitizer;

/**
 * Redacts sensitive HTTP header values (case-insensitive keys).
 */
class HeaderSanitizer extends RecursiveArraySanitizer
{
    /**
     * Create a header sanitizer for the given name patterns.
     *
     * @param list<string> $patterns Header name patterns.
     * @param string $replacement Replacement marker.
     */
    public function __construct(
        array $patterns = [],
        string $replacement = self::DEFAULT_REPLACEMENT,
    ) {
        parent::__construct($patterns, $replacement);
    }

    /**
     * @inheritDoc
     */
    public function sanitize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $name => $headerValue) {
            $key = strtolower((string)$name);
            if (is_array($headerValue)) {
                $normalized[$key] = implode(', ', array_map(strval(...), $headerValue));
                continue;
            }

            $normalized[$key] = (string)$headerValue;
        }

        return parent::sanitize($normalized);
    }
}
