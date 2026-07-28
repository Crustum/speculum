<?php
declare(strict_types=1);

namespace Crustum\Speculum\Sanitizer;

/**
 * Redacts SQL binding values by named-key pattern, SQL column context, or omit-all.
 *
 * When SQL assigns/filters any sensitive column (`password`, `token`, …) via a
 * placeholder, every binding for that statement is replaced (not only the secret slot).
 */
class QueryBindingSanitizer extends RecursiveArraySanitizer
{
    /**
     * Create a query-binding sanitizer for the given patterns and SQL context.
     *
     * @param list<string> $patterns Named binding / column key patterns.
     * @param string $replacement Replacement marker.
     * @param bool $omit When true, discard all bindings.
     * @param string|null $sql SQL used to infer column ↔ placeholder mapping.
     */
    public function __construct(
        array $patterns = [],
        string $replacement = self::DEFAULT_REPLACEMENT,
        protected bool $omit = false,
        protected ?string $sql = null,
    ) {
        parent::__construct($patterns, $replacement);
    }

    /**
     * @inheritDoc
     */
    public function sanitize(mixed $value): mixed
    {
        if ($this->omit) {
            return [];
        }

        if (!is_array($value)) {
            return $value;
        }

        /** @var array<array-key, mixed> $sanitized */
        $sanitized = parent::sanitize($value);

        if ($this->sql === null || trim($this->sql) === '') {
            return $sanitized;
        }

        return $this->redactUsingSqlContext($sanitized, $this->sql);
    }

    /**
     * When any placeholder maps to a sensitive column, redact all bindings.
     *
     * @param array<array-key, mixed> $bindings Bindings.
     * @param string $sql SQL statement.
     * @return array<array-key, mixed>
     */
    protected function redactUsingSqlContext(array $bindings, string $sql): array
    {
        if (!$this->sqlTouchesSensitiveColumn($sql)) {
            return $bindings;
        }

        return $this->redactAllValues($bindings);
    }

    /**
     * Whether SQL placeholder context includes a sensitive column.
     *
     * @param string $sql SQL statement.
     * @return bool
     */
    protected function sqlTouchesSensitiveColumn(string $sql): bool
    {
        $map = $this->inferPlaceholderColumns($sql);

        return array_any(
            [...array_values($map['positional']), ...array_values($map['named'])],
            fn(string $column): bool => $column !== '' && $this->matches($column),
        );
    }

    /**
     * Replace every scalar binding value with the redaction marker.
     *
     * @param array<array-key, mixed> $bindings Bindings.
     * @return array<array-key, mixed>
     */
    protected function redactAllValues(array $bindings): array
    {
        foreach ($bindings as $key => $item) {
            if (is_array($item)) {
                $bindings[$key] = $this->redactAllValues($item);
                continue;
            }

            $bindings[$key] = $this->replacement;
        }

        return $bindings;
    }

    /**
     * Map placeholders to column names from SET/WHERE assignments and INSERT lists.
     *
     * @param string $sql SQL statement.
     * @return array{positional: array<int, string>, named: array<string, string>}
     */
    protected function inferPlaceholderColumns(string $sql): array
    {
        $positional = [];
        $named = [];
        $positionalIndex = 0;

        $normalized = preg_replace('/\s+/', ' ', $sql) ?? $sql;

        if (
            preg_match(
                '/insert\s+into\s+[`"\[]?\w+[`"\]]?\s*\(([^)]+)\)\s*values\s*\(([^)]+)\)/i',
                $normalized,
                $insert,
            ) === 1
        ) {
            $columns = $this->splitSqlList($insert[1]);
            $values = $this->splitSqlList($insert[2]);
            $count = min(count($columns), count($values));
            for ($i = 0; $i < $count; $i++) {
                $column = $this->normalizeIdentifier($columns[$i]);
                $placeholder = trim($values[$i]);
                if ($column === '') {
                    continue;
                }

                if ($placeholder === '?') {
                    $positional[$positionalIndex++] = $column;
                    continue;
                }

                if (preg_match('/^:([\w]+)$/', $placeholder, $namedMatch) === 1) {
                    $named[$namedMatch[1]] = $column;
                }
            }
        }

        if (
            preg_match_all(
                '/(?:[`"\[]?)([A-Za-z_][\w.]*)(?:[`"\]]?)\s*=\s*(\?|:[\w]+)/',
                $normalized,
                $assignments,
                PREG_SET_ORDER,
            ) > 0
        ) {
            foreach ($assignments as $assignment) {
                $column = $this->normalizeIdentifier($assignment[1]);
                $placeholder = $assignment[2];
                if ($column === '') {
                    continue;
                }

                if ($placeholder === '?') {
                    $positional[$positionalIndex++] = $column;
                    continue;
                }

                if (preg_match('/^:([\w]+)$/', $placeholder, $namedMatch) === 1) {
                    $named[$namedMatch[1]] = $column;
                }
            }
        }

        return [
            'positional' => $positional,
            'named' => $named,
        ];
    }

    /**
     * Split a comma-separated SQL list, ignoring commas inside quotes.
     *
     * @param string $list Column or value list.
     * @return list<string>
     */
    protected function splitSqlList(string $list): array
    {
        $parts = [];
        $current = '';
        $quote = null;
        $length = strlen($list);

        for ($i = 0; $i < $length; $i++) {
            $char = $list[$i];
            if ($quote !== null) {
                $current .= $char;
                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if (in_array($char, ["'", '"', '`'], true)) {
                $quote = $char;
                $current .= $char;
                continue;
            }

            if ($char === ',') {
                $parts[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }

    /**
     * Strip quoting / table prefix from an identifier.
     *
     * @param string $identifier Raw identifier.
     * @return string
     */
    protected function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        $identifier = trim($identifier, " \t\n\r\0\x0B`\"[]");
        if (str_contains($identifier, '.')) {
            $parts = explode('.', $identifier);
            $identifier = end($parts);
            $identifier = trim($identifier, " \t\n\r\0\x0B`\"[]");
        }

        return $identifier;
    }
}
