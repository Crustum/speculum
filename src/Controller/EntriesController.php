<?php
declare(strict_types=1);

namespace Crustum\Speculum\Controller;

use Cake\Controller\Controller;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Crustum\Speculum\Contract\ClearableRepository;
use Crustum\Speculum\Speculum;

/**
 * Clear all Speculum entries.
 */
class EntriesController extends Controller
{
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
     * Clear all stored Speculum entries.
     *
     * @return \Cake\Http\Response
     */
    public function delete(): Response
    {
        $storage = Speculum::getRepository();
        if ($storage instanceof ClearableRepository) {
            $storage->clear();
        }

        return $this->response->withStatus(200);
    }
}
