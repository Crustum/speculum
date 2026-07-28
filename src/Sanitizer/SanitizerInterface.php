<?php
declare(strict_types=1);

namespace Crustum\Speculum\Sanitizer;

/**
 * Contract for Speculum sensitive-data sanitizers.
 */
interface SanitizerInterface
{
    /**
     * Redact sensitive values from the given payload.
     *
     * @param mixed $value Raw value.
     * @return mixed
     */
    public function sanitize(mixed $value): mixed;
}
