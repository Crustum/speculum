<?php
declare(strict_types=1);

namespace Crustum\Speculum\Controller;

use Cake\Controller\Controller;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Crustum\Speculum\Speculum;

/**
 * Monitored tags API.
 */
class MonitoredTagsController extends Controller
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
     * List monitored tags.
     *
     * @return \Cake\Http\Response|null
     */
    public function index(): ?Response
    {
        $this->set('tags', Speculum::getRepository()->monitoring());
        $this->viewBuilder()->setOption('serialize', ['tags']);

        return null;
    }

    /**
     * Start monitoring a tag.
     *
     * @return \Cake\Http\Response
     */
    public function add(): Response
    {
        $tag = (string)$this->request->getData('tag', $this->request->getQuery('tag'));
        if ($tag !== '') {
            Speculum::getRepository()->monitor([$tag]);
        }

        return $this->response->withStatus(200);
    }

    /**
     * Stop monitoring a tag.
     *
     * @return \Cake\Http\Response
     */
    public function delete(): Response
    {
        $tag = (string)$this->request->getData('tag', $this->request->getQuery('tag'));
        if ($tag !== '') {
            Speculum::getRepository()->stopMonitoring([$tag]);
        }

        return $this->response->withStatus(200);
    }
}
