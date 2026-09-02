<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher\Mongo;

use Cake\Core\Configure;
use Cake\Database\Log\LoggedQuery;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Driver logger decorator for Crustum Mongo (binds before QueryLogger formats).
 *
 * Analogue of {@see \Crustum\Speculum\Watcher\SpeculumQueryLogger}.
 */
class SpeculumMongoQueryLogger extends AbstractLogger
{
    /**
     * Wrap an optional inner driver logger.
     *
     * @param \Psr\Log\LoggerInterface|null $logger Inner logger.
     */
    public function __construct(
        protected ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Return the decorated inner logger.
     *
     * @return \Psr\Log\LoggerInterface|null
     */
    public function getInnerLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @inheritDoc
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $query = $context['query'] ?? null;
        if ($query instanceof LoggedQuery) {
            $this->recordLoggedQuery($query, (string)$message, $context);
        }

        if ($this->logger instanceof LoggerInterface) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * Record a LoggedQuery into Speculum as a Mongo query entry.
     *
     * @param \Cake\Database\Log\LoggedQuery $logged Logged query.
     * @param string $message Log message (JSON payload when from MongoLogger).
     * @param array<string, mixed> $context Log context.
     * @return void
     */
    protected function recordLoggedQuery(LoggedQuery $logged, string $message, array $context): void
    {
        if (!Speculum::isRecording() || !WatcherRegistry::has(CrustumMongoWatcher::class)) {
            return;
        }

        $serialized = $logged->jsonSerialize();
        $queryText = (string)($serialized['query'] ?? '');
        if ($queryText === '') {
            $queryText = $message !== '' ? $message : (string)$logged;
        }

        $queryContext = $logged->getContext();
        $time = (float)($queryContext['took'] ?? $serialized['took'] ?? $context['duration_ms'] ?? 0);
        $connection = $logged->getConnectionName();
        if ($connection === '' && isset($queryContext['connection'])) {
            $connection = (string)$queryContext['connection'];
        }

        if ($connection === '') {
            $connection = isset($context['connection']) ? (string)$context['connection'] : null;
        }

        $extra = [
            'operation' => $context['operation'] ?? null,
            'database' => $context['database'] ?? null,
            'collection' => $context['collection'] ?? null,
        ];

        $options = Configure::read('Speculum.watchers.' . CrustumMongoWatcher::class, []);
        $watcher = new CrustumMongoWatcher(is_array($options) ? $options : []);
        $watcher->record($queryText, $time, $connection !== '' ? $connection : null, $extra);
    }
}
