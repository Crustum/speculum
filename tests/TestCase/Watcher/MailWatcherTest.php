<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\Mailer\Message;
use Cake\Mailer\Transport\DebugTransport;
use Cake\Mailer\TransportFactory;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Mailer\Transport\SpeculumTransport;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\MailWatcher;

/**
 * Mail watcher tests.
 */
class MailWatcherTest extends TestCaseBase
{
    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        TransportFactory::drop('speculum_test');
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        TransportFactory::drop('speculum_test');
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testMailWatcherRegistersEntry(): void
    {
        $message = new Message();
        $message
            ->setFrom(['from@cakephp.org' => 'From'])
            ->setTo(['to@cakephp.org' => 'To'])
            ->setCc(['cc1@cakephp.org' => 'CC1', 'cc2@cakephp.org' => 'CC2'])
            ->setBcc(['bcc@cakephp.org' => 'BCC'])
            ->setSubject('Check this out!')
            ->setBodyText('Speculum is amazing!');

        $watcher = new MailWatcher(['enabled' => true]);
        $watcher->record($message);

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Mail->value, $entry->type);
        $this->assertSame('', $entry->content['mailable']);
        $this->assertFalse($entry->content['queued']);
        $this->assertSame(['from@cakephp.org'], array_keys($entry->content['from']));
        $this->assertSame(['to@cakephp.org'], array_keys($entry->content['to']));
        $this->assertSame(['cc1@cakephp.org', 'cc2@cakephp.org'], array_keys($entry->content['cc']));
        $this->assertSame(['bcc@cakephp.org'], array_keys($entry->content['bcc']));
        $this->assertSame('Check this out!', $entry->content['subject']);
        $this->assertSame('Speculum is amazing!', $entry->content['html']);
        $raw = $entry->content['raw'];
        $this->assertIsString($raw);
        $this->assertStringContainsString('From:', $raw);
        $this->assertStringContainsString('To:', $raw);
        $this->assertStringContainsString('Subject: Check this out!', $raw);
        $this->assertStringContainsString('MIME-Version: 1.0', $raw);
        $this->assertStringContainsString('Speculum is amazing!', $raw);
        $this->assertMatchesRegularExpression("/\r\n\r\nSpeculum is amazing!/", $raw);
    }

    /**
     * @return void
     */
    public function testMailWatcherRecordsThroughWrappedTransport(): void
    {
        TransportFactory::setConfig('speculum_test', [
            'className' => DebugTransport::class,
        ]);

        $watcher = new MailWatcher(['enabled' => true]);
        $watcher->wrapConfiguredTransports();

        $transport = TransportFactory::get('speculum_test');
        $this->assertInstanceOf(SpeculumTransport::class, $transport);

        $message = new Message();
        $message
            ->setFrom(['from@example.com' => 'From'])
            ->setTo(['to@example.com' => 'To'])
            ->setSubject('Wrapped transport')
            ->setBodyText('Hello from DebugTransport');

        $result = $transport->send($message);
        $this->assertArrayHasKey('headers', $result);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(EntryType::Mail->value, $entries[0]->type);
        $this->assertSame('Wrapped transport', $entries[0]->content['subject']);
        $this->assertSame(['to@example.com'], array_keys($entries[0]->content['to']));
        $this->assertSame(DebugTransport::class, $entries[0]->content['mailable']);
    }
}
