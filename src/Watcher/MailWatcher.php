<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\Mailer\Message;
use Cake\Mailer\TransportFactory;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Mailer\Transport\SpeculumTransport;
use Crustum\Speculum\Speculum;
use Throwable;

/**
 * Records outbound mail messages.
 *
 * CakePHP Mailer does not dispatch send events, so transports are wrapped.
 */
class MailWatcher extends Watcher
{
    /**
     * @inheritDoc
     */
    public function register(): void
    {
        $this->wrapConfiguredTransports();

        EventManager::instance()->on('Mailer.send', function (EventInterface $event): void {
            $this->recordFromEvent($event);
        });

        EventManager::instance()->on('Email.send', function (EventInterface $event): void {
            $this->recordFromEvent($event);
        });
    }

    /**
     * Wrap every configured mail transport so sends are recorded.
     *
     * @return void
     */
    public function wrapConfiguredTransports(): void
    {
        $registry = TransportFactory::getRegistry();

        foreach (TransportFactory::configured() as $name) {
            try {
                $transport = TransportFactory::get($name);
            } catch (Throwable) {
                continue;
            }

            if ($transport instanceof SpeculumTransport) {
                continue;
            }

            $registry->set($name, new SpeculumTransport($transport, $this));
        }
    }

    /**
     * Record a mail send from a Cake event.
     *
     * @param \Cake\Event\EventInterface<object> $event Mail event.
     * @return void
     */
    public function recordFromEvent(EventInterface $event): void
    {
        $message = $event->getData('message') ?? $event->getSubject();
        if ($message instanceof Message) {
            $this->record($message, (array)$event->getData());
        }
    }

    /**
     * Record a mail message entry.
     *
     * @param \Cake\Mailer\Message $message Mail message.
     * @param array<string, mixed> $data Event data.
     * @return void
     */
    public function record(Message $message, array $data = []): void
    {
        if (!Speculum::isRecording()) {
            return;
        }

        $html = null;
        $raw = null;
        try {
            $html = $message->getBodyHtml() ?: $message->getBodyText();
            $raw = $this->formatRawMessage($message);
        } catch (Throwable) {
            $html = null;
            $raw = null;
        }

        $from = $this->formatAddresses($message->getFrom());
        $to = $this->formatAddresses($message->getTo());
        $cc = $this->formatAddresses($message->getCc());
        $bcc = $this->formatAddresses($message->getBcc());

        $entry = IncomingEntry::make([
            'mailable' => (string)($data['mailable'] ?? $data['email'] ?? $data['transport'] ?? ''),
            'queued' => (bool)($data['queued'] ?? false),
            'from' => $from,
            'replyTo' => $this->formatAddresses($message->getReplyTo()),
            'to' => $to,
            'cc' => $cc,
            'bcc' => $bcc,
            'subject' => $message->getSubject(),
            'html' => $html,
            'raw' => $raw,
        ]);

        $entry->tags(array_values(array_unique(array_merge(
            array_keys($to ?? []),
            array_keys($cc ?? []),
            array_keys($bcc ?? []),
        ))));

        Speculum::recordEntry(EntryType::Mail, $entry);
    }

    /**
     * Build an RFC822-style string (headers + body) for .eml download.
     *
     * @param \Cake\Mailer\Message $message Mail message.
     * @return string
     */
    protected function formatRawMessage(Message $message): string
    {
        $headers = $message->getHeadersString([
            'from',
            'sender',
            'replyTo',
            'readReceipt',
            'returnPath',
            'to',
            'cc',
            'bcc',
            'subject',
        ]);
        $body = $message->getBodyString();

        return $headers . "\r\n\r\n" . $body;
    }

    /**
     * Normalize address maps for Speculum storage.
     *
     * @param array<string, string> $addresses Address map.
     * @return array<string, string>|null
     */
    protected function formatAddresses(array $addresses): ?array
    {
        return $addresses !== [] ? $addresses : null;
    }
}
