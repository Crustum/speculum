<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher\Mongo;

use Cake\Datasource\ConnectionManager;
use Cake\Event\EventManager;
use Cake\ORM\Table;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Watcher\Watcher;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use Throwable;

/**
 * Soft Crustum Mongo ODM query watcher (driver logger wrap).
 *
 * Optional analogue of {@see \Crustum\Speculum\Watcher\QueryWatcher} for
 * `Crustum\Mongo\Database\Driver\MongoDriver`. Registers only when
 * SoftFeature::CrustumMongo is available.
 */
class CrustumMongoWatcher extends Watcher
{
    /**
     * Driver object ids already wrapped for this process.
     *
     * @var array<int, true>
     */
    protected static array $wrappedDrivers = [];

    /**
     * Crustum Mongo driver FQCN (optional dependency).
     */
    protected const DRIVER_CLASS = 'Crustum\\Mongo\\Database\\Driver\\MongoDriver';

    /**
     * Reset wrap tracking (tests).
     *
     * @return void
     */
    public static function resetWrapped(): void
    {
        static::$wrappedDrivers = [];
    }

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        if (!WatcherRegistry::isSoftAvailable(SoftFeature::CrustumMongo)) {
            return;
        }

        $this->installDriverLoggers();

        EventManager::instance()->on('Model.initialize', function ($event): void {
            $subject = $event->getSubject();
            if (!$subject instanceof Table) {
                return;
            }

            try {
                $connection = $subject->getConnection();
                $name = $connection->configName();
                if ($this->shouldIgnoreConnection($name)) {
                    return;
                }

                $driver = $connection->getDriver();
                $driverClass = self::DRIVER_CLASS;
                if (!$driver instanceof $driverClass) {
                    return;
                }

                $this->wrapDriverLogger($driver);
            } catch (Throwable) {
            }
        });
    }

    /**
     * Wrap configured Crustum Mongo connection drivers.
     *
     * DebugKit and other tools call `setLogger()` on every connection (including
     * `log => false`), which enables the global APM subscriber. We still wrap
     * every non-ignored Mongo driver so Speculum stays in the logger chain;
     * ignored connections are detached so they do not duplicate traffic.
     *
     * @return void
     */
    protected function installDriverLoggers(): void
    {
        $driverClass = self::DRIVER_CLASS;
        if (!class_exists($driverClass)) {
            return;
        }

        foreach (ConnectionManager::configured() as $name) {
            $name = (string)$name;

            try {
                $driver = ConnectionManager::get($name)->getDriver();
                if (!$driver instanceof $driverClass) {
                    continue;
                }

                if ($this->shouldIgnoreConnection($name)) {
                    if (
                        method_exists($driver, 'disableQueryLogging') && method_exists($driver, 'isQueryLoggingEnabled')
                        && $driver->isQueryLoggingEnabled()
                    ) {
                        $driver->disableQueryLogging();
                    }

                    continue;
                }

                $this->wrapDriverLogger($driver);
            } catch (Throwable) {
            }
        }
    }

    /**
     * Decorate a Mongo driver logger with SpeculumMongoQueryLogger.
     *
     * @param object $driver Crustum MongoDriver instance.
     * @return void
     */
    protected function wrapDriverLogger(object $driver): void
    {
        if (!method_exists($driver, 'getLogger') || !method_exists($driver, 'setLogger')) {
            return;
        }

        $current = $driver->getLogger();
        if ($this->loggerChainHasSpeculum($current)) {
            static::$wrappedDrivers[spl_object_id($driver)] = true;

            return;
        }

        $driver->setLogger(new SpeculumMongoQueryLogger(
            $current instanceof LoggerInterface ? $current : null,
        ));
        static::$wrappedDrivers[spl_object_id($driver)] = true;
    }

    /**
     * Whether SpeculumMongoQueryLogger is already present in the logger chain.
     *
     * @param \Psr\Log\LoggerInterface|null $logger Outermost driver logger.
     * @return bool
     */
    protected function loggerChainHasSpeculum(?LoggerInterface $logger): bool
    {
        $current = $logger;
        for ($i = 0; $i < 8 && $current instanceof LoggerInterface; $i++) {
            if ($current instanceof SpeculumMongoQueryLogger) {
                return true;
            }

            if (method_exists($current, 'getInnerLogger')) {
                $next = $current->getInnerLogger();
                $current = $next instanceof LoggerInterface ? $next : null;
                continue;
            }

            try {
                $property = new ReflectionProperty($current, '_logger');
                $inner = $property->getValue($current);
                if ($inner instanceof LoggerInterface) {
                    $current = $inner;
                    continue;
                }
            } catch (Throwable) {
            }

            break;
        }

        return false;
    }

    /**
     * Record a Crustum Mongo query entry (driver path).
     *
     * @param string $query Encoded command / payload text.
     * @param float $time Duration in ms.
     * @param string|null $connection Connection name.
     * @param array<string, mixed> $context Extra context (operation, database, …).
     * @return void
     */
    public function record(
        string $query,
        float $time,
        ?string $connection = null,
        array $context = [],
    ): void {
        if (!Speculum::isRecording()) {
            return;
        }

        if ($this->shouldIgnoreConnection($connection)) {
            return;
        }

        $query = trim($query);
        if ($query === '') {
            return;
        }

        $caller = $this->getCallerFromStackTrace();
        $slow = $this->isSlowDuration($time);

        Speculum::recordEntry(EntryType::MongoQuery, IncomingEntry::make([
            'connection' => $connection,
            'query' => $query,
            'operation' => $context['operation'] ?? null,
            'database' => $context['database'] ?? null,
            'collection' => $context['collection'] ?? null,
            'time' => number_format($time, 2, '.', ''),
            'slow' => $slow,
            'file' => $caller['file'] ?? null,
            'line' => $caller['line'] ?? 0,
            'hash' => md5($query),
            'source' => 'driver',
        ])->tags($this->slowTags($time))->withFamilyHash(md5($query)));
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

            if (str_contains($file, 'Mongo' . DIRECTORY_SEPARATOR . 'crustum' . DIRECTORY_SEPARATOR . 'src')) {
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
