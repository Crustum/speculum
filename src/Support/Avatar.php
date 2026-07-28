<?php
declare(strict_types=1);

namespace Crustum\Speculum\Support;

/**
 * Resolve avatar URLs for entry users.
 */
class Avatar
{
    /**
     * Optional custom avatar URL resolver.
     *
     * @var callable|null
     */
    protected static $callback;

    /**
     * Register a custom avatar URL resolver.
     *
     * @param callable $callback Avatar URL resolver.
     * @return void
     */
    public static function register(callable $callback): void
    {
        static::$callback = $callback;
    }

    /**
     * Resolve an avatar URL for the given user payload.
     *
     * @param array<string, mixed> $user User payload from entry content.
     * @return string|null
     */
    public static function url(array $user): ?string
    {
        if (static::$callback) {
            return (string)(static::$callback)($user);
        }

        if (empty($user['email']) || !is_string($user['email'])) {
            return null;
        }

        return 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user['email']))) . '?s=200';
    }
}
