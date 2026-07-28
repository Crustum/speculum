<?php
declare(strict_types=1);

namespace Crustum\Speculum\Controller;

use Cake\Controller\Controller;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Crustum\Speculum\Speculum;

/**
 * Toggle Speculum recording pause flag.
 */
class RecordingController extends Controller
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
     * Toggle the Speculum recording pause flag.
     *
     * @return \Cake\Http\Response
     */
    public function toggle(): Response
    {
        if (Speculum::isRecordingPaused()) {
            Speculum::resumeRecording();
        } else {
            Speculum::pauseRecording();
        }

        return $this->response->withStatus(200);
    }
}
