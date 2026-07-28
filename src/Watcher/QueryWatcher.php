<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Database\Driver;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventManager;
use Cake\ORM\Table;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Sanitizer\SensitiveData;
use Crustum\Speculum\Speculum;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Records SQL queries via a Cake database query logger bridge.
 */
class QueryWatcher extends Watcher
{
    /**
     * Driver object ids already wrapped for this process.
     *
     * @var array<int, true>
     */
    protected static array $wrappedDrivers = [];

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        $this->installDriverLoggers();

        EventManager::instance()->on('Model.initialize', function ($event): void {
            $subject = $event->getSubject();
            if (!$subject instanceof Table) {
                return;
            }

            try {
                $connection = $subject->getConnection();
                if ($this->shouldIgnoreConnection($connection->configName())) {
                    return;
                }

                $driver = $connection->getDriver();
                $this->wrapDriverLogger($driver);
                $driver->enableQueryLogging();
            } catch (Throwable) {
            }
        });
    }

    /**
     * Wrap configured connection drivers so LoggedQuery params are captured.
     *
     * @return void
     */
    protected function installDriverLoggers(): void
    {
        foreach (ConnectionManager::configured() as $name) {
            if ($this->shouldIgnoreConnection((string)$name)) {
                continue;
            }

            try {
                $driver = ConnectionManager::get((string)$name)->getDriver();
                if (!$driver instanceof Driver) {
                    continue;
                }

                $this->wrapDriverLogger($driver);
                $driver->enableQueryLogging();
            } catch (Throwable) {
            }
        }
    }

    /**
     * Decorate a driver logger with SpeculumQueryLogger when needed.
     *
     * Skips if this driver instance was already wrapped so later plugins
     * (e.g. Rhythm) can sit outside without creating a second Speculum layer.
     *
     * @param \Cake\Database\Driver $driver Database driver.
     * @return void
     */
    protected function wrapDriverLogger(Driver $driver): void
    {
        $driverId = spl_object_id($driver);
        if (isset(static::$wrappedDrivers[$driverId])) {
            return;
        }

        $current = $driver->getLogger();
        if ($current instanceof SpeculumQueryLogger) {
            static::$wrappedDrivers[$driverId] = true;

            return;
        }

        $driver->setLogger(new SpeculumQueryLogger(
            $current instanceof LoggerInterface ? $current : null,
        ));
        static::$wrappedDrivers[$driverId] = true;
    }

    /**
     * Record a SQL query entry.
     *
     * @param string $sql SQL string.
     * @param float $time Duration in ms.
     * @param string|null $connection Connection name.
     * @param string|null $driver Driver name.
     * @param array<string|int, mixed> $bindings Bound parameters.
     * @return void
     */
    public function record(
        string $sql,
        float $time,
        ?string $connection = null,
        ?string $driver = null,
        array $bindings = [],
    ): void {
        if (!Speculum::isRecording()) {
            return;
        }

        if ($this->shouldIgnoreConnection($connection)) {
            return;
        }

        $sql = $this->normalizeSql($sql);
        if ($sql === '' || $this->shouldIgnoreSql($sql)) {
            return;
        }

        $caller = $this->getCallerFromStackTrace();
        $slow = $this->isSlowDuration($time);

        Speculum::recordEntry(EntryType::Query, IncomingEntry::make([
            'connection' => $connection,
            'driver' => $driver,
            'bindings' => SensitiveData::bindings(
                $this->normalizeBindings($bindings),
                $sql,
            ),
            'sql' => $sql,
            'time' => number_format($time, 2, '.', ''),
            'slow' => $slow,
            'file' => $caller['file'] ?? null,
            'line' => $caller['line'] ?? 0,
            'hash' => md5($sql),
        ])->tags($this->slowTags($time))->withFamilyHash(md5($sql)));
    }

    /**
     * Whether the connection name is configured to be ignored.
     *
     * @param string|null $connection Connection name.
     * @return bool
     */
    protected function shouldIgnoreConnection(?string $connection): bool
    {
        if ($connection === null || $connection === '') {
            return false;
        }

        $ignored = array_values(array_map(strval(...), $this->options['ignore_connections'] ?? []));

        return in_array($connection, $ignored, true);
    }

    /**
     * Whether SQL targets Speculum storage tables and must not be recorded.
     *
     * @param string $sql Normalized SQL.
     * @return bool
     */
    protected function shouldIgnoreSql(string $sql): bool
    {
        return (bool)preg_match('/\bspeculum_\w+/i', $sql);
    }

    /**
     * Normalize bound parameters for JSON storage.
     *
     * @param array<string|int, mixed> $bindings Bindings.
     * @return array<string|int, mixed>
     */
    protected function normalizeBindings(array $bindings): array
    {
        $normalized = [];
        foreach ($bindings as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = $this->normalizeBindings($value);
                continue;
            }

            if ($value instanceof DateTimeInterface) {
                $normalized[$key] = $value->format('Y-m-d H:i:s');
                continue;
            }

            if (is_object($value)) {
                $normalized[$key] = $value::class;
                continue;
            }

            if (is_resource($value)) {
                $normalized[$key] = 'resource';
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * Strip Cake QueryLogger message prefixes that break sql-formatter.
     *
     * @param string $sql Raw SQL or log message.
     * @return string
     */
    public function normalizeSql(string $sql): string
    {
        $sql = trim($sql);
        if (
            preg_match(
                '/^connection=(?:\{connection\}|\S+)\s+role=(?:\{role\}|\S+)\s+duration=(?:\{took\}|\S+)\s+rows=(?:\{numRows\}|\S+)\s+(.+)$/s',
                $sql,
                $matches,
            )
        ) {
            return trim($matches[1]);
        }

        return $sql;
    }

    /**
     * Find the first non-vendor caller frame for query attribution.
     *
     * @return array{file?: string, line?: int}|null
     */
    protected function getCallerFromStackTrace(): ?array
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            $file = $frame['file'] ?? null;
            if (!$file) {
                continue;
            }

            if (str_contains($file, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
                continue;
            }

            if (str_contains($file, 'Speculum' . DIRECTORY_SEPARATOR . 'src')) {
                continue;
            }

            return [
                'file' => $file,
                'line' => $frame['line'] ?? 0,
            ];
        }

        return null;
    }
}
