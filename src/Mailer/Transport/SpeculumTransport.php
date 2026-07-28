<?php
declare(strict_types=1);

namespace Crustum\Speculum\Mailer\Transport;

use Cake\Mailer\AbstractTransport;
use Cake\Mailer\Message;
use Crustum\Speculum\Watcher\MailWatcher;

/**
 * Decorates a mail transport and records sends for Speculum.
 */
class SpeculumTransport extends AbstractTransport
{
    /**
     * Wrap a mail transport with Speculum recording.
     *
     * @param \Cake\Mailer\AbstractTransport $inner Real transport.
     * @param \Crustum\Speculum\Watcher\MailWatcher $watcher Mail watcher.
     */
    public function __construct(
        protected AbstractTransport $inner,
        protected MailWatcher $watcher,
    ) {
        parent::__construct($inner->getConfig());
    }

    /**
     * @inheritDoc
     */
    public function send(Message $message): array
    {
        $result = $this->inner->send($message);
        $this->watcher->record($message, [
            'mailable' => $this->inner::class,
            'transport' => $this->inner::class,
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * Return the decorated inner mail transport.
     *
     * @return \Cake\Mailer\AbstractTransport
     */
    public function getInnerTransport(): AbstractTransport
    {
        return $this->inner;
    }
}
