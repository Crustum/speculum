<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Error\PhpError;
use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Cake\Event\EventManager;
use Cake\View\View;
use Crustum\Speculum\Entry\IncomingExceptionEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Support\ExceptionContext;
use Crustum\Speculum\Support\ExceptionLocation;
use ErrorException;
use Throwable;

/**
 * Records exceptions via Exception.beforeRender (Otel-style) and Error view fallback.
 */
class ExceptionWatcher extends Watcher implements EventListenerInterface
{
    /**
     * Fingerprints already recorded in this process/request.
     *
     * @var array<string, true>
     */
    protected static array $recorded = [];

    /**
     * Reset per-request dedupe (tests / long-running workers).
     *
     * @return void
     */
    public static function resetRecorded(): void
    {
        static::$recorded = [];
    }

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        EventManager::instance()->on($this);
    }

    /**
     * Return the Cake events handled by this watcher.
     *
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return [
            'Exception.beforeRender' => [
                ['priority' => 0, 'callable' => 'onExceptionBeforeRender'],
            ],
            'Error.beforeRender' => [
                ['priority' => 0, 'callable' => 'onErrorBeforeRender'],
            ],
            'View.beforeRender' => [
                ['priority' => 0, 'callable' => 'onViewBeforeRender'],
            ],
        ];
    }

    /**
     * Record an exception from Exception.beforeRender.
     *
     * @param \Cake\Event\EventInterface<object> $event Exception.beforeRender event.
     * @return void
     */
    public function onExceptionBeforeRender(EventInterface $event): void
    {
        $exception = $event->getData('exception');
        if ($exception instanceof Throwable) {
            $this->recordException($exception);
        }
    }

    /**
     * Record a PHP error from Error.beforeRender.
     *
     * @param \Cake\Event\EventInterface<object> $event Error.beforeRender event.
     * @return void
     */
    public function onErrorBeforeRender(EventInterface $event): void
    {
        $error = $event->getData('error');
        if ($error instanceof PhpError) {
            $this->recordException(new ErrorException(
                $error->getMessage(),
                0,
                $error->getCode(),
                $error->getFile() ?? '',
                $error->getLine() ?? 0,
            ));

            return;
        }

        if (is_object($error) && method_exists($error, 'getException')) {
            $exception = $error->getException();
            if ($exception instanceof Throwable) {
                $this->recordException($exception);
            }
        }
    }

    /**
     * Fallback when error400/error500 views render (same path Speculum already sees).
     *
     * @param \Cake\Event\EventInterface<object> $event View.beforeRender event.
     * @param mixed $viewFile View file path.
     * @return void
     */
    public function onViewBeforeRender(EventInterface $event, mixed $viewFile = null): void
    {
        $view = $event->getSubject();
        if (!$view instanceof View) {
            return;
        }

        $template = $view->getTemplate();
        $file = (string)$viewFile;
        $isErrorTemplate = in_array($template, ['error400', 'error500'], true)
            || str_contains(str_replace('\\', '/', $file), '/Error/error');

        if (!$isErrorTemplate) {
            return;
        }

        $error = $view->get('error');
        if ($error instanceof Throwable) {
            $this->recordException($error);
        }
    }

    /**
     * Record an exception entry for Speculum storage.
     *
     * @param \Throwable $exception Exception.
     * @param array<string, mixed> $context Log context.
     * @return void
     */
    public function recordException(Throwable $exception, array $context = []): void
    {
        $location = ExceptionLocation::fromThrowable($exception);
        $fingerprint = md5(
            $exception::class . '|' . $location['file'] . '|' . $location['line'] . '|' . $exception->getMessage(),
        );
        if (isset(static::$recorded[$fingerprint])) {
            return;
        }

        if (!Speculum::isRecording()) {
            return;
        }

        static::$recorded[$fingerprint] = true;

        $trace = [];
        foreach ($exception->getTrace() as $item) {
            $trace[] = array_intersect_key($item, array_flip(['file', 'line']));
        }

        $extra = $context;
        unset($extra['exception'], $extra['speculum']);

        $tags = [];
        $speculumTags = $context['speculum'] ?? null;
        if (is_array($speculumTags)) {
            foreach ($speculumTags as $tag) {
                if (is_string($tag)) {
                    $tags[] = $tag;
                }
            }
        }

        Speculum::recordEntry(
            EntryType::Exception,
            IncomingExceptionEntry::make($exception, [
                'class' => $exception::class,
                'file' => $location['file'],
                'line' => $location['line'],
                'message' => $exception->getMessage(),
                'context' => $extra !== [] ? $extra : null,
                'trace' => $trace,
                'line_preview' => ExceptionContext::get($exception),
            ])->tags($tags),
        );
    }
}
