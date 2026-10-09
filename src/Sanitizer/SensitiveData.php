<?php
declare(strict_types=1);

namespace Crustum\Speculum\Sanitizer;

use Cake\Core\Configure;
use Crustum\Speculum\Speculum;

/**
 * Facade that builds sanitizers from Speculum static lists + Configure.
 */
class SensitiveData
{
    /**
     * Default request/response/model parameter patterns.
     *
     * @var list<string>
     */
    public const DEFAULT_PARAMETER_PATTERNS = [
        '*password*',
        '*token*',
        '*secret*',
        '*api_key*',
        '*apikey*',
        'authorization',
        'php_auth_pw',
        'php-auth-pw',
    ];

    /**
     * Default HTTP header patterns.
     *
     * @var list<string>
     */
    public const DEFAULT_HEADER_PATTERNS = [
        'authorization',
        'proxy-authorization',
        'php-auth-pw',
        'cookie',
        'set-cookie',
        'x-xsrf-token',
        'x-csrf-token',
    ];

    /**
     * Redact request/response-style arrays (nested keys supported).
     *
     * @param array<array-key, mixed> $data Payload.
     * @param list<string>|null $patterns Extra patterns; null uses request parameters.
     * @param list<string> $excludeKeys Leaf keys/paths whose whole subtree is left untouched.
     * @return array<array-key, mixed>
     */
    public static function parameters(array $data, ?array $patterns = null, array $excludeKeys = []): array
    {
        $patterns ??= static::parameterPatterns();

        /** @var array<array-key, mixed> $result */
        $result = (new RecursiveArraySanitizer($patterns, AbstractPatternSanitizer::DEFAULT_REPLACEMENT, true, $excludeKeys))->sanitize($data);

        return $result;
    }

    /**
     * Redact response JSON bodies.
     *
     * @param array<array-key, mixed> $data Payload.
     * @return array<array-key, mixed>
     */
    public static function responseParameters(array $data): array
    {
        return static::parameters($data, static::responseParameterPatterns());
    }

    /**
     * Redact model change attributes.
     *
     * @param array<array-key, mixed> $data Payload.
     * @return array<array-key, mixed>
     */
    public static function modelAttributes(array $data): array
    {
        return static::parameters($data, static::modelAttributePatterns());
    }

    /**
     * Redact VarDump array payloads (same defaults as models + Users secrets).
     *
     * @param array<array-key, mixed> $data Payload.
     * @return array<array-key, mixed>
     */
    public static function varDumpAttributes(array $data): array
    {
        return static::parameters($data, static::varDumpPatterns());
    }

    /**
     * Glob patterns used when cloning values for VarDump HTML.
     *
     * @return list<string>
     */
    public static function varDumpPatterns(): array
    {
        return static::uniquePatterns([
            ...self::DEFAULT_PARAMETER_PATTERNS,
            ...Speculum::$hiddenModelAttributes,
            ...Speculum::$hiddenRequestParameters,
            ...static::configList('model_attributes'),
            ...static::configList('parameters'),
            ...static::configList('vardump'),
        ]);
    }

    /**
     * Redact HTTP headers.
     *
     * @param array<array-key, mixed> $headers Headers.
     * @return array<string, string>
     */
    public static function headers(array $headers): array
    {
        /** @var array<string, string> $result */
        $result = (new HeaderSanitizer(static::headerPatterns()))->sanitize($headers);

        return $result;
    }

    /**
     * Redact or omit SQL bindings.
     *
     * Named keys and SQL-inferred columns (`SET password = ?`, INSERT lists) are
     * matched against binding patterns. If any sensitive column is touched, all
     * bindings for that statement are redacted. Opaque Cake names (`:c0`) rely on SQL.
     *
     * @param array<array-key, mixed> $bindings Bindings.
     * @param string|null $sql SQL statement for positional / `:cN` inference.
     * @return array<array-key, mixed>
     */
    public static function bindings(array $bindings, ?string $sql = null): array
    {
        $config = Configure::read('Speculum.sanitize.bindings', []);
        $omit = is_array($config) && (bool)($config['omit'] ?? false);
        $patterns = static::bindingPatterns();

        /** @var array<array-key, mixed> $result */
        $result = (new QueryBindingSanitizer(
            $patterns,
            AbstractPatternSanitizer::DEFAULT_REPLACEMENT,
            $omit,
            $sql,
        ))->sanitize($bindings);

        return $result;
    }

    /**
     * Sanitize a JSON-decoded or array payload; leave non-arrays untouched.
     *
     * @param mixed $payload Payload.
     * @param list<string>|null $patterns Parameter patterns.
     * @param list<string> $excludeKeys Leaf keys whose whole subtree is left untouched.
     * @return mixed
     */
    public static function payload(mixed $payload, ?array $patterns = null, array $excludeKeys = []): mixed
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                $sanitized = static::parameters($decoded, $patterns, $excludeKeys);

                return json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $payload;
            }

            return $payload;
        }

        if (is_array($payload)) {
            return static::parameters($payload, $patterns, $excludeKeys);
        }

        return $payload;
    }

    /**
     * Merge Configure `Speculum.sanitize` lists into Speculum static hide lists.
     *
     * Safe to call from bootstrap / Speculum::start().
     *
     * @return void
     */
    public static function syncFromConfig(): void
    {
        $sanitize = Configure::read('Speculum.sanitize', []);
        if (!is_array($sanitize)) {
            return;
        }

        $headers = $sanitize['headers'] ?? [];
        if (is_array($headers) && $headers !== []) {
            Speculum::hideRequestHeaders(array_values(array_filter($headers, is_string(...))));
        }

        $parameters = $sanitize['parameters'] ?? [];
        if (is_array($parameters) && $parameters !== []) {
            Speculum::hideRequestParameters(array_values(array_filter($parameters, is_string(...))));
        }

        $response = $sanitize['response_parameters'] ?? [];
        if (is_array($response) && $response !== []) {
            Speculum::hideResponseParameters(array_values(array_filter($response, is_string(...))));
        }

        $model = $sanitize['model_attributes'] ?? [];
        if (is_array($model) && $model !== []) {
            Speculum::hideModelAttributes(array_values(array_filter($model, is_string(...))));
        }
    }

    /**
     * Header name patterns used when sanitizing requests.
     *
     * @return list<string>
     */
    protected static function headerPatterns(): array
    {
        return static::uniquePatterns([
            ...self::DEFAULT_HEADER_PATTERNS,
            ...Speculum::$hiddenRequestHeaders,
            ...static::configList('headers'),
        ]);
    }

    /**
     * Request parameter patterns used when sanitizing payloads.
     *
     * @return list<string>
     */
    protected static function parameterPatterns(): array
    {
        return static::uniquePatterns([
            ...self::DEFAULT_PARAMETER_PATTERNS,
            ...Speculum::$hiddenRequestParameters,
            ...static::configList('parameters'),
        ]);
    }

    /**
     * Response parameter patterns used when sanitizing response content.
     *
     * @return list<string>
     */
    protected static function responseParameterPatterns(): array
    {
        return static::uniquePatterns([
            ...self::DEFAULT_PARAMETER_PATTERNS,
            ...Speculum::$hiddenResponseParameters,
            ...static::configList('response_parameters'),
        ]);
    }

    /**
     * Model attribute patterns used when sanitizing entity changes.
     *
     * @return list<string>
     */
    protected static function modelAttributePatterns(): array
    {
        return static::uniquePatterns([
            ...self::DEFAULT_PARAMETER_PATTERNS,
            ...Speculum::$hiddenModelAttributes,
            ...static::configList('model_attributes'),
        ]);
    }

    /**
     * SQL binding key patterns used when sanitizing query bindings.
     *
     * @return list<string>
     */
    protected static function bindingPatterns(): array
    {
        $bindings = Configure::read('Speculum.sanitize.bindings', []);
        $extra = [];
        if (is_array($bindings) && isset($bindings['patterns']) && is_array($bindings['patterns'])) {
            $extra = array_values(array_filter($bindings['patterns'], is_string(...)));
        }

        return static::uniquePatterns([
            ...self::DEFAULT_PARAMETER_PATTERNS,
            ...$extra,
        ]);
    }

    /**
     * Read a string-list sanitize config value under Speculum.sanitize.
     *
     * @param string $key Configure sub-key under Speculum.sanitize.
     * @return list<string>
     */
    protected static function configList(string $key): array
    {
        $value = Configure::read('Speculum.sanitize.' . $key, []);
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    /**
     * Deduplicate and drop empty pattern strings.
     *
     * @param list<string> $patterns Patterns.
     * @return list<string>
     */
    protected static function uniquePatterns(array $patterns): array
    {
        return array_values(array_unique(array_filter($patterns, static fn(string $p): bool => $p !== '')));
    }
}
