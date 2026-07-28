<?php
declare(strict_types=1);

namespace Crustum\Speculum\Queue;

use Cake\Core\Configure;
use Cake\Log\Log;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Registry\WatcherRegistry;
use Throwable;

/**
 * Resolves and invokes the configured Speculum job transport.
 */
class JobDispatcher
{
    /**
     * Override used in tests.
     *
     * @var \Crustum\Speculum\Queue\JobDispatcherInterface|null
     */
    protected static ?JobDispatcherInterface $instance = null;

    /**
     * Push via the resolved transport.
     *
     * @param array{pendingUpdates?: list<array<string, mixed>>, attempt?: int}|array<string, mixed> $data Job data.
     * @param array<string, mixed> $options Transport options (`config`, `queue`, `delay`, …).
     * @return void
     */
    public static function push(array $data, array $options = []): void
    {
        try {
            static::resolve()->push($data, $options);
        } catch (Throwable $throwable) {
            Log::warning('Speculum could not queue pending updates: ' . $throwable->getMessage());
        }
    }

    /**
     * Resolve the active dispatcher (config override or auto-detect).
     *
     * @param string|null $transport Transport name override.
     * @return \Crustum\Speculum\Queue\JobDispatcherInterface
     */
    public static function resolve(?string $transport = null): JobDispatcherInterface
    {
        if (static::$instance instanceof JobDispatcherInterface) {
            return static::$instance;
        }

        $transport ??= (string)Configure::read('Speculum.queue.transport', 'auto');
        $transport = strtolower(trim($transport));

        return match ($transport) {
            'cakephp', 'cake', 'cakephp/queue' => new CakeQueueJobDispatcher(),
            'dereuromark', 'queue' => new DereuromarkJobDispatcher(),
            'queuesadilla' => new QueuesadillaJobDispatcher(),
            default => static::resolveAuto(),
        };
    }

    /**
     * Replace the resolved dispatcher (tests).
     *
     * @param \Crustum\Speculum\Queue\JobDispatcherInterface|null $dispatcher Dispatcher or null to clear.
     * @return void
     */
    public static function setInstance(?JobDispatcherInterface $dispatcher): void
    {
        static::$instance = $dispatcher;
    }

    /**
     * Auto-detect: cakephp → Dereuromark → Queuesadilla → null.
     *
     * @return \Crustum\Speculum\Queue\JobDispatcherInterface
     */
    protected static function resolveAuto(): JobDispatcherInterface
    {
        $candidates = [
            new CakeQueueJobDispatcher(),
            new DereuromarkJobDispatcher(),
            new QueuesadillaJobDispatcher(),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate->isAvailable()) {
                return $candidate;
            }
        }

        return new NullJobDispatcher();
    }

    /**
     * Soft feature used by cakephp/queue transport and JobWatcher consume path.
     *
     * @return bool
     */
    public static function isCakeQueueAvailable(): bool
    {
        return WatcherRegistry::isSoftAvailable(SoftFeature::CakeQueue);
    }
}
