<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry\Inspection;

use Crustum\Speculum\Contract\EntriesRepository;
use Crustum\Speculum\Entry\EntryResult;
use Crustum\Speculum\Entry\Presentation\GenericEntryPresentation;
use Crustum\Speculum\Entry\Presentation\PresentationRegistry;
use Crustum\Speculum\Entry\Routing\EntryTypeMap;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Storage\EntryQueryOptions;

/**
 * Lists Speculum entries for human and agent consumers.
 *
 * Returns plain data consumed by `speculum list` (table or JSON passthrough)
 * and, later, MCP tools. Commands calling this service stay thin.
 */
final class EntryListing
{
    /**
     * Default list limit.
     *
     * @var int
     */
    public const DEFAULT_LIMIT = 20;

    /**
     * Create an entry listing service.
     *
     * @param \Crustum\Speculum\Contract\EntriesRepository $entries Entries repository.
     */
    public function __construct(
        private readonly EntriesRepository $entries,
    ) {
    }

    /**
     * List entries with filters.
     *
     * @param string|null $typeInput Entry type value or API resource path.
     * @param array{tag?: mixed, batch?: mixed, family?: mixed, limit?: mixed, before?: mixed} $filters Filters.
     * @return array{entries: list<array<string,mixed>>, headers: list<string>, rows: list<list<string>>, pagination: array{count: int, limit: int, nextBefore: string|int|null}}
     * @throws \Crustum\Speculum\Entry\Inspection\EntryInspectionException
     */
    public function list(?string $typeInput, array $filters = []): array
    {
        $type = $this->resolveListType($typeInput);
        $limit = $this->resolveLimit($filters['limit'] ?? self::DEFAULT_LIMIT);

        $batchFilter = $this->stringOrNull($filters['batch'] ?? null);
        if ($batchFilter !== null && strlen($batchFilter) < 36) {
            $batchIds = $this->entries->batchIdsByPrefix($batchFilter);
            if ($batchIds === []) {
                return $this->emptyList($type, $limit);
            }

            $batchFilter = $batchIds;
        }

        $options = (new EntryQueryOptions())
            ->tag($this->stringOrNull($filters['tag'] ?? null))
            ->batchId($batchFilter)
            ->familyHash($this->stringOrNull($filters['family'] ?? null))
            ->beforeSequence($filters['before'] ?? null)
            ->limit($limit);

        /** @var list<\Crustum\Speculum\Entry\EntryResult> $results */
        $results = $this->entries->get($type, $options);

        $presentation = is_string($type) && $type !== ''
            ? PresentationRegistry::for($type)
            : PresentationRegistry::generic();

        $rows = [];
        foreach ($results as $result) {
            if (is_string($type) && $type !== '') {
                $rows[] = $presentation->tableRow($result);
            } else {
                $rows[] = PresentationRegistry::for($result->type)->tableRow($result);
            }
        }

        $count = count($results);
        $last = $count > 0 ? $results[$count - 1] : null;

        return [
            'entries' => array_map(
                static fn(EntryResult $result): array => $result->jsonSerialize(),
                $results,
            ),
            'headers' => $presentation->tableHeaders(),
            'rows' => $rows,
            'pagination' => [
                'count' => $count,
                'limit' => $limit,
                'nextBefore' => $count >= $limit && $last !== null ? $last->sequence : null,
            ],
        ];
    }

    /**
     * List known entry types with resource paths and descriptions.
     *
     * Types are gathered from the enum plus presentation registrations, so
     * extension types appear without a hardcoded list. Storage is untouched.
     *
     * @return array{headers: list<string>, types: list<array{type: string, resource: string, description: string}>}
     */
    public function types(): array
    {
        $resources = [];
        foreach (EntryTypeMap::all() as $path => $type) {
            foreach ((array)$type as $value) {
                $resources[$value] ??= $path;
            }
        }

        $values = EntryType::all();
        foreach (PresentationRegistry::registeredTypes() as $registered) {
            if (!in_array($registered, $values, true)) {
                $values[] = $registered;
            }
        }

        $types = [];
        foreach ($values as $value) {
            $presentation = PresentationRegistry::for($value);
            $types[] = [
                'type' => $value,
                'resource' => $resources[$value] ?? '',
                'description' => $presentation instanceof GenericEntryPresentation
                    ? $presentation->describe()
                    : '',
            ];
        }

        return [
            'headers' => ['Type', 'Resource', 'Description'],
            'types' => $types,
        ];
    }

    /**
     * Build an empty listing payload for unmatched short batch prefixes.
     *
     * @param list<string>|string|null $type Resolved type filter.
     * @param int $limit Resolved limit.
     * @return array{entries: list<array<string,mixed>>, headers: list<string>, rows: list<list<string>>, pagination: array{count: int, limit: int, nextBefore: string|int|null}}
     */
    protected function emptyList(string|array|null $type, int $limit): array
    {
        $presentation = is_string($type) && $type !== ''
            ? PresentationRegistry::for($type)
            : PresentationRegistry::generic();

        return [
            'entries' => [],
            'headers' => $presentation->tableHeaders(),
            'rows' => [],
            'pagination' => [
                'count' => 0,
                'limit' => $limit,
                'nextBefore' => null,
            ],
        ];
    }

    /**
     * Resolve the CLI type argument to a repository type filter.
     *
     * @param string|null $typeInput CLI type argument.
     * @return list<string>|string|null
     * @throws \Crustum\Speculum\Entry\Inspection\EntryInspectionException
     */
    protected function resolveListType(?string $typeInput): string|array|null
    {
        if ($typeInput === null || $typeInput === '') {
            return null;
        }

        $type = PresentationRegistry::resolveType($typeInput);
        if ($type === null) {
            throw new EntryInspectionException(
                'Invalid entry type: ' . $typeInput
                . "\nValid types: " . implode(', ', PresentationRegistry::validTypes()),
            );
        }

        return $type;
    }

    /**
     * Resolve the list limit to a positive integer.
     *
     * @param mixed $limit Raw limit value.
     * @return int
     * @throws \Crustum\Speculum\Entry\Inspection\EntryInspectionException
     */
    protected function resolveLimit(mixed $limit): int
    {
        if (is_int($limit) && $limit >= 1) {
            return $limit;
        }

        if (is_string($limit) && ctype_digit($limit) && (int)$limit >= 1) {
            return (int)$limit;
        }

        throw new EntryInspectionException('The --limit option must be a positive integer.');
    }

    /**
     * Cast a filter value to string or null.
     *
     * @param mixed $value Raw value.
     * @return string|null
     */
    protected function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string)$value;
    }
}
