<?php
declare(strict_types=1);

namespace Crustum\Speculum\Sanitizer;

/**
 * Base sanitizer that matches keys against glob-style patterns.
 */
abstract class AbstractPatternSanitizer implements SanitizerInterface
{
    public const DEFAULT_REPLACEMENT = '(REDACTED)';

    /**
     * Create a pattern sanitizer with replacement options.
     *
     * @param list<string> $patterns Glob patterns (`password`, `password*`, `*token*`).
     * @param string $replacement Replacement marker.
     * @param bool $caseInsensitive Whether key matching is case-insensitive.
     */
    public function __construct(
        protected array $patterns = [],
        protected string $replacement = self::DEFAULT_REPLACEMENT,
        protected bool $caseInsensitive = true,
    ) {
    }

    /**
     * Whether the given key matches any configured pattern.
     *
     * Patterns may target the leaf key or a dotted path (`user.password`, `*.token`).
     *
     * @param string $key Leaf key or dotted path.
     * @return bool
     */
    protected function matches(string $key): bool
    {
        if ($key === '' || $this->patterns === []) {
            return false;
        }

        $flags = $this->caseInsensitive ? FNM_CASEFOLD : 0;
        foreach ($this->patterns as $pattern) {
            if ($pattern === '') {
                continue;
            }

            if (fnmatch($pattern, $key, $flags)) {
                return true;
            }
        }

        return false;
    }
}
