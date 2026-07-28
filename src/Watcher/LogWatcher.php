<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Log\Log;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Speculum;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Records application log messages at or above the configured level.
 *
 * Cake engine `scopes` is always `[]` (receive every Cake scope). Speculum then
 * filters with watcher options:
 *
 * - `scopes` null: all messages (default; covers BlazeCast `socket.server*`, etc.)
 * - `scopes` []: unscoped messages only
 * - `scopes` ['payment', …]: those Cake scopes; unscoped included when
 *   `include_unscoped` is true (default)
 */
class LogWatcher extends Watcher
{
    /**
     * PSR log level name to numeric severity map.
     *
     * @var array<string, int>
     */
    protected array $levels = [
        LogLevel::DEBUG => 100,
        LogLevel::INFO => 200,
        LogLevel::NOTICE => 250,
        LogLevel::WARNING => 300,
        LogLevel::ERROR => 400,
        LogLevel::CRITICAL => 500,
        LogLevel::ALERT => 550,
        LogLevel::EMERGENCY => 600,
    ];

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        if (Log::getConfig('speculum')) {
            Log::drop('speculum');
        }

        Log::setConfig('speculum', [
            'className' => SpeculumLogEngine::class,
            'levels' => [],
            'scopes' => [],
        ]);
    }

    /**
     * Record a log message entry.
     *
     * @param string $level Log level.
     * @param string $message Message.
     * @param array<string, mixed> $context Context.
     * @return void
     */
    public function record(string $level, string $message, array $context = []): void
    {
        if (!Speculum::isRecording() || !$this->isLevelEnabled($level) || !$this->isScopeEnabled($context)) {
            return;
        }

        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            return;
        }

        Speculum::recordEntry(EntryType::Log, IncomingEntry::make([
            'level' => $level,
            'message' => $message,
            'context' => $context !== [] ? $context : null,
        ]));
    }

    /**
     * Determine whether the log level meets the configured minimum.
     *
     * @param string $level Log level.
     * @return bool
     */
    protected function isLevelEnabled(string $level): bool
    {
        $minimum = strtolower((string)($this->options['level'] ?? 'error'));
        $current = strtolower($level);
        $minValue = $this->levels[$minimum] ?? 400;
        $curValue = $this->levels[$current] ?? 0;

        return $curValue >= $minValue;
    }

    /**
     * Determine whether Cake log scopes are allowed by watcher options.
     *
     * @param array<string, mixed> $context Log context.
     * @return bool
     */
    protected function isScopeEnabled(array $context): bool
    {
        $configured = $this->options['scopes'] ?? null;
        if ($configured === null) {
            return true;
        }

        if (!is_array($configured)) {
            return true;
        }

        $messageScopes = $this->normalizeScopes($context['scope'] ?? []);
        $includeUnscoped = (bool)($this->options['include_unscoped'] ?? true);

        if ($messageScopes === []) {
            return $configured === [] ? true : $includeUnscoped;
        }

        if ($configured === []) {
            return false;
        }

        return array_intersect($messageScopes, $configured) !== [];
    }

    /**
     * Normalize Cake `scope` context to a list of scope names.
     *
     * @param mixed $scope Context scope value.
     * @return list<string>
     */
    protected function normalizeScopes(mixed $scope): array
    {
        if (in_array($scope, [null, '', []], true)) {
            return [];
        }

        if (!is_array($scope)) {
            return [(string)$scope];
        }

        $normalized = [];
        foreach ($scope as $item) {
            if ($item === null) {
                continue;
            }

            if ($item === '') {
                continue;
            }

            $normalized[] = (string)$item;
        }

        return $normalized;
    }
}
