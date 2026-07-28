<?php
declare(strict_types=1);

namespace Crustum\Speculum\Controller;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Crustum\Speculum\Registry\WatcherRegistry;

/**
 * Serves the Speculum Vue SPA shell.
 *
 * Dashboard access is owned by the host application (middleware / ACL / debug-only
 * plugin load). This controller does not allowUnauthenticated or skipAuthorization.
 */
class HomeController extends Controller
{
    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setPlugin('Crustum/Speculum');
    }

    /**
     * Set layout and plugin for Speculum SPA.
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event The beforeFilter event.
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);

        $this->viewBuilder()->setLayout('speculum');
        $this->viewBuilder()->setPlugin('Crustum/Speculum');
    }

    /**
     * Display the Speculum SPA.
     *
     * @return void
     */
    public function index(): void
    {
        $path = (string)Configure::read('Speculum.path', 'speculum');
        $timezone = (string)Configure::read('App.defaultTimezone', date_default_timezone_get() ?: 'UTC');
        $recording = (bool)Configure::read('Speculum.recording', true);
        $availableWatchers = WatcherRegistry::availableWatchers();
        $root = defined('ROOT')
            ? str_replace('\\', '/', rtrim((string)constant('ROOT'), '/\\'))
            : '';
        $editor = (string)Configure::read('Debugger.editor', 'phpstorm');

        $speculumScript = [
            'path' => $path,
            'timezone' => $timezone,
            'recording' => $recording,
            'availableWatchers' => $availableWatchers,
            'root' => $root,
            'editor' => $editor,
        ];

        $this->set([
            'path' => $path,
            'timezone' => $timezone,
            'recording' => $recording,
            'availableWatchers' => $availableWatchers,
            'root' => $root,
            'speculumScript' => $speculumScript,
        ]);
    }
}
