<?php
declare(strict_types=1);

namespace Crustum\Speculum\Registry;

use Crustum\Speculum\Enum\SoftFeature;

/**
 * Registered Speculum entry API resource (list/show under `/speculum/api/{path}`).
 */
final class EntryResource
{
    /**
     * Create an entry API resource definition.
     *
     * @param string $path URL segment under `/speculum/api`.
     * @param list<string>|string $type Entry type value(s) for repository queries.
     * @param class-string<\Crustum\Speculum\Watcher\Watcher> $watcher Watcher class for status checks.
     * @param \Crustum\Speculum\Enum\SoftFeature|array<\Crustum\Speculum\Enum\SoftFeature>|null $soft Soft feature gate(s) for status `off`.
     *     When an array is given, the resource is considered available if *any* of the soft features is available.
     */
    public function __construct(
        public readonly string $path,
        public readonly string|array $type,
        public readonly string $watcher,
        public readonly SoftFeature|array|null $soft = null,
    ) {
    }
}
