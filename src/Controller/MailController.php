<?php
declare(strict_types=1);

namespace Crustum\Speculum\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Watcher\MailWatcher;
use Throwable;

/**
 * Speculum API controller for mail entries.
 */
class MailController extends EntryController
{
    /**
     * @inheritDoc
     */
    protected function entryType(): string
    {
        return EntryType::Mail->value;
    }

    /**
     * @inheritDoc
     */
    protected function watcher(): string
    {
        return MailWatcher::class;
    }

    /**
     * HTML preview of a mail entry.
     *
     * Served with CSP sandbox so scripts cannot run as Speculum (same-origin XSS).
     * The SPA iframe must also use `sandbox` without allow-scripts / allow-same-origin.
     *
     * @param string $id Entry UUID.
     * @return \Cake\Http\Response
     */
    public function preview(string $id): Response
    {
        try {
            $entry = $this->entries->find($id);
        } catch (Throwable) {
            throw new NotFoundException();
        }

        $html = (string)($entry->content['html'] ?? '');

        return $this->response
            ->withType('html')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader(
                'Content-Security-Policy',
                "sandbox; default-src 'none'; img-src * data: https: http:; style-src 'unsafe-inline' data:; font-src * data:; media-src *; base-uri 'none'; form-action 'none'; frame-ancestors 'self'",
            )
            ->withStringBody($html);
    }

    /**
     * Download raw EML content for a mail entry.
     *
     * @param string $id Entry UUID.
     * @return \Cake\Http\Response
     */
    public function download(string $id): Response
    {
        try {
            $entry = $this->entries->find($id);
        } catch (Throwable) {
            throw new NotFoundException();
        }

        $raw = (string)($entry->content['raw'] ?? $entry->content['html'] ?? '');

        return $this->response
            ->withType('message/rfc822')
            ->withDownload('speculum-mail-' . $id . '.eml')
            ->withStringBody($raw);
    }
}
