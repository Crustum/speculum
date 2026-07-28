<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Event\EventManager;
use Cake\View\View;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Speculum;

/**
 * Records view renders.
 */
class ViewWatcher extends Watcher
{
    /**
     * @inheritDoc
     */
    public function register(): void
    {
        EventManager::instance()->on('View.beforeRender', function ($event, $viewFile): void {
            $this->record($event->getSubject(), (string)$viewFile);
        });
    }

    /**
     * Record a view render entry.
     *
     * @param mixed $view View instance.
     * @param string $viewFile View file.
     * @return void
     */
    public function record(mixed $view, string $viewFile): void
    {
        if (!Speculum::isRecording()) {
            return;
        }

        if ($this->shouldIgnorePath($viewFile)) {
            return;
        }

        $name = $viewFile;
        $path = $viewFile;
        $dataKeys = [];

        if ($view instanceof View) {
            $name = $view->getTemplate();
            $path = $viewFile;
            $dataKeys = array_values(array_filter(
                $view->getVars(),
                static fn(string $var): bool => !in_array($var, [
                    '_serialized',
                    '_serialize',
                    '_jsonOptions',
                    '_jsonp',
                ], true),
            ));
        }

        Speculum::recordEntry(EntryType::View, IncomingEntry::make(array_filter([
            'name' => $name,
            'path' => $path,
            'data' => $dataKeys !== [] ? $dataKeys : null,
            'composers' => null,
        ], static fn($value): bool => $value !== null)));
    }

    /**
     * Determine whether the view path should be ignored.
     *
     * @param string $viewFile View file path.
     * @return bool
     */
    protected function shouldIgnorePath(string $viewFile): bool
    {
        $normalized = str_replace('\\', '/', $viewFile);

        if (
            str_contains($normalized, '/Speculum/templates/')
            || str_contains($normalized, '/Crustum/Speculum/')
        ) {
            return true;
        }

        $patterns = array_values(array_map(strval(...), $this->options['ignore_paths'] ?? []));

        return array_any(
            $patterns,
            static fn(string $pattern): bool => $pattern !== '' && fnmatch($pattern, $normalized),
        );
    }
}
