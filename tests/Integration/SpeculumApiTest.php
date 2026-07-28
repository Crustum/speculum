<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\Integration;

use Cake\Cache\Cache;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Crustum\Speculum\Test\TestCase\IntegrationTestCase;

/**
 * HTTP integration coverage for /speculum/api.
 */
class SpeculumApiTest extends IntegrationTestCase
{
    /**
     * @return void
     */
    public function testMetaReturnsAvailableWatchers(): void
    {
        $this->disableErrorHandlerMiddleware();
        $this->get('/speculum/api/meta');
        $this->assertResponseOk();
        $body = $this->jsonBody();
        $this->assertArrayHasKey('availableWatchers', $body);
        $this->assertContains('requests', $body['availableWatchers']);
        $this->assertContains('vardumps', $body['availableWatchers']);
    }

    /**
     * @return void
     */
    public function testRequestsIndexReturnsEntryContract(): void
    {
        $entry = IncomingEntry::make([
            'uri' => '/demo',
            'method' => 'GET',
            'response_status' => 200,
        ])->type(EntryType::Request->value)->batchId('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $this->repository->store([$entry]);

        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->post('/speculum/api/requests', ['take' => 10]);
        $this->assertResponseOk();
        $body = $this->jsonBody();
        $this->assertArrayHasKey('entries', $body);
        $this->assertArrayHasKey('status', $body);
        $this->assertNotEmpty($body['entries']);
        $first = $body['entries'][0];
        foreach (['id', 'sequence', 'batch_id', 'type', 'content', 'tags', 'family_hash', 'created', 'duration'] as $key) {
            $this->assertArrayHasKey($key, $first);
        }

        $this->assertSame(EntryType::Request->value, $first['type']);
    }

    /**
     * @return void
     */
    public function testRequestShowReturnsEntryAndBatch(): void
    {
        $batchId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $entry = IncomingEntry::make([
            'uri' => '/show-me',
            'method' => 'POST',
            'response_status' => 201,
        ])->type(EntryType::Request->value)->batchId($batchId);
        $this->repository->store([$entry]);
        $stored = $this->repository->get(EntryType::Request->value, new EntryQueryOptions());
        $this->assertNotEmpty($stored);
        $id = $stored[0]->id;

        $this->get('/speculum/api/requests/' . $id);
        $this->assertResponseOk();
        $body = $this->jsonBody();
        $this->assertArrayHasKey('entry', $body);
        $this->assertArrayHasKey('batch', $body);
        $this->assertSame($id, $body['entry']['id']);
        $this->assertSame(EntryType::Request->value, $body['entry']['type']);
    }

    /**
     * @return void
     */
    public function testToggleRecording(): void
    {
        Cache::delete(Speculum::PAUSE_CACHE_KEY);
        $this->post('/speculum/api/toggle-recording');
        $this->assertResponseOk();
        $this->assertTrue((bool)Cache::read(Speculum::PAUSE_CACHE_KEY));
        $this->post('/speculum/api/toggle-recording');
        $this->assertResponseOk();
        $this->assertFalse((bool)Cache::read(Speculum::PAUSE_CACHE_KEY));
    }

    /**
     * @return void
     */
    public function testMailPreviewSetsSandboxCspAndNosniff(): void
    {
        $entry = IncomingEntry::make([
            'html' => '<html><body><script>window.top.location="/pwned"</script><p>hi</p></body></html>',
            'subject' => 'xss',
            'from' => ['a@b.c' => null],
            'to' => ['c@d.e' => null],
            'mailable' => '',
            'queued' => false,
        ])->type(EntryType::Mail->value)->batchId('cccccccc-cccc-cccc-cccc-cccccccccccc');
        $this->repository->store([$entry]);
        $stored = $this->repository->get(EntryType::Mail->value, new EntryQueryOptions());
        $this->assertNotEmpty($stored);
        $id = $stored[0]->id;

        $this->get('/speculum/api/mail/' . $id . '/preview');
        $this->assertResponseOk();
        $this->assertResponseContains('<p>hi</p>');
        $this->assertHeaderContains('Content-Security-Policy', 'sandbox');
        $this->assertHeaderContains('Content-Security-Policy', "default-src 'none'");
        $this->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /**
     * @return void
     */
    public function testHomeSpaShellLoads(): void
    {
        $this->disableErrorHandlerMiddleware();
        $this->get('/speculum');
        $this->assertResponseOk();
        $this->assertResponseContains('id="speculum"');
        $this->assertResponseContains('window.Speculum');
        $body = (string)$this->_response->getBody();
        $this->assertStringContainsString('/frontend/styles.css', $body);
        $this->assertStringContainsString('/frontend/app.css', $body);
        $this->assertStringContainsString('/frontend/app.js', $body);
    }

    /**
     * @return void
     */
    public function testFrontendAssetsPresentOnDisk(): void
    {
        $base = ROOT . DS . 'webroot' . DS . 'frontend' . DS;
        $this->assertFileExists($base . 'app.js');
        $this->assertFileExists($base . 'app.css');
        $this->assertFileExists($base . 'styles.css');
        $this->assertFileExists($base . 'styles-dark.css');
        $this->assertFileExists($base . 'manifest.json');
    }
}
