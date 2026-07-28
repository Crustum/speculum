<?php
declare(strict_types=1);

namespace Crustum\Speculum\Controller;

use Cake\Controller\Controller;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Crustum\Speculum\Registry\WatcherRegistry;

/**
 * Soft-dependency meta endpoint for the Vue nav.
 */
class MetaController extends Controller
{
    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * @inheritDoc
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        if ($this->components()->has('FormProtection')) {
            $this->FormProtection->setConfig('validate', false);
        }
    }

    /**
     * Return available watcher screen keys.
     *
     * @return \Cake\Http\Response|null
     */
    public function index(): ?Response
    {
        $this->set('availableWatchers', WatcherRegistry::availableWatchers());
        $this->viewBuilder()->setOption('serialize', ['availableWatchers']);

        return null;
    }
}
