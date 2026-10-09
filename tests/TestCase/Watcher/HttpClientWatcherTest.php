<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\Http\Client;
use Cake\Http\Client\ClientEvent;
use Cake\Http\Client\Request;
use Cake\Http\Client\Response;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\HttpClientWatcher;

/**
 * HTTP client watcher tests.
 */
class HttpClientWatcherTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testHttpClientWatcherRegistersSuccessfulRequestAndResponse(): void
    {
        $watcher = new HttpClientWatcher(['enabled' => true]);
        $watcher->record(
            'GET',
            'https://cakephp.org/foo/bar',
            ['Accept-Language' => 'en_US'],
            null,
            [
                'status' => 201,
                'headers' => [
                    'content-type' => 'application/json',
                    'cache-control' => 'no-cache,private',
                ],
                'body' => '{"foo":"bar"}',
            ],
            42,
        );

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::HttpClient->value, $entry->type);
        $this->assertSame('GET', $entry->content['method']);
        $this->assertSame('https://cakephp.org/foo/bar', $entry->content['uri']);
        $this->assertSame('en_US', $entry->content['headers']['accept-language']);
        $this->assertSame(201, $entry->content['response_status']);
        $this->assertSame(['foo' => 'bar'], $entry->content['response']);
        $this->assertSame(42, $entry->content['duration']);
    }

    /**
     * @return void
     */
    public function testHttpClientWatcherKeepsUsageCountersUnredacted(): void
    {
        $watcher = new HttpClientWatcher(['enabled' => true]);
        $watcher->record(
            'POST',
            'https://api.openai.com/v1/chat/completions',
            ['Content-Type' => 'application/json'],
            ['model' => 'gpt-4o', 'api_key' => 'secret-key'],
            [
                'status' => 200,
                'headers' => ['content-type' => 'application/json'],
                'body' => json_encode([
                    'choices' => [['message' => ['content' => 'hi']]],
                    'usage' => [
                        'prompt_tokens' => 12,
                        'completion_tokens' => 34,
                        'total_tokens' => 46,
                    ],
                    'api_key' => 'secret-key',
                ]),
            ],
            42,
        );

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(12, $entry->content['response']['usage']['prompt_tokens']);
        $this->assertSame(34, $entry->content['response']['usage']['completion_tokens']);
        $this->assertSame(46, $entry->content['response']['usage']['total_tokens']);
        $this->assertSame('(REDACTED)', $entry->content['response']['api_key']);
        $this->assertSame('(REDACTED)', $entry->content['payload']['api_key']);
    }

    /**
     * @return void
     */
    public function testHttpClientWatcherReadsCakeAfterSendEvent(): void
    {
        $client = new Client();
        $request = new Request('https://api.github.com/users/octocat', 'GET');
        $response = new Response(['HTTP/1.1 200 OK', 'Content-Type: application/json'], '{"login":"octocat"}');

        $before = new ClientEvent('HttpClient.beforeSend', $client, [
            'request' => $request,
            'adapterOptions' => [],
            'redirects' => 0,
        ]);
        $after = new ClientEvent('HttpClient.afterSend', $client, [
            'request' => $request,
            'adapterOptions' => [],
            'redirects' => 0,
            'requestSent' => true,
            'response' => $response,
        ]);

        $watcher = new HttpClientWatcher(['enabled' => true]);
        $watcher->beforeSend($before);
        usleep(1000);
        $watcher->afterSend($after);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(EntryType::HttpClient->value, $entries[0]->type);
        $this->assertSame('GET', $entries[0]->content['method']);
        $this->assertSame('https://api.github.com/users/octocat', $entries[0]->content['uri']);
        $this->assertSame(200, $entries[0]->content['response_status']);
        $this->assertSame(['login' => 'octocat'], $entries[0]->content['response']);
        $this->assertIsInt($entries[0]->content['duration']);
        $this->assertGreaterThanOrEqual(0, $entries[0]->content['duration']);
    }

    /**
     * @return void
     */
    public function testHttpClientWatcherRespectsIgnoreHosts(): void
    {
        $watcher = new HttpClientWatcher([
            'enabled' => true,
            'ignore_hosts' => ['*.internal'],
        ]);
        $watcher->record('GET', 'https://api.internal/health');
        $watcher->record('GET', 'https://example.com/ok');

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('https://example.com/ok', $entries[0]->content['uri']);
    }

    /**
     * @return void
     */
    public function testHttpClientWatcherStoresPlaceholderForBinaryZipResponse(): void
    {
        $zip = "PK\x03\x04" . str_repeat("\x00\xFF", 32);
        $watcher = new HttpClientWatcher(['enabled' => true, 'size_limit' => 64]);
        $watcher->record(
            'GET',
            'https://codeload.github.com/acme/demo/legacy.zip/main',
            [],
            null,
            [
                'status' => 200,
                'headers' => [
                    'content-type' => 'application/zip',
                ],
                'body' => $zip,
            ],
        );

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(
            sprintf('Binary Response (%d bytes)', strlen($zip)),
            $entries[0]->content['response'],
        );
        $this->assertNotFalse(json_encode($entries[0]->content));
    }

    /**
     * @return void
     */
    public function testHttpClientWatcherStoresPlaceholderForInvalidUtf8WithoutContentType(): void
    {
        $binary = "hello\x80\xFF" . str_repeat("\x01", 8);
        $watcher = new HttpClientWatcher(['enabled' => true]);
        $response = new Response(['HTTP/1.1 200 OK'], $binary);

        $watcher->recordFromHttp(
            new Request('https://example.com/file.bin', 'GET'),
            $response,
            10,
        );

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(
            sprintf('Binary Response (%d bytes)', strlen($binary)),
            $entries[0]->content['response'],
        );
        $this->assertNotFalse(json_encode($entries[0]->content));
    }

    /**
     * @return void
     */
    public function testStreamedResponsePatchesEntryOnAfterSendStream(): void
    {
        $client = new Client();
        $request = new Request('https://api.openai.com/v1/chat', 'POST');
        $sse = "data: {\"token\":\"hi\"}\n\ndata: [DONE]\n\n";

        $watcher = new HttpClientWatcher(['enabled' => true]);

        $before = new ClientEvent('HttpClient.beforeSend', $client, [
            'request' => $request,
            'adapterOptions' => [],
        ]);
        $after = new ClientEvent('HttpClient.afterSend', $client, [
            'request' => $request,
            'adapterOptions' => [],
            'response' => new Response(['HTTP/1.1 200 OK', 'Content-Type: text/event-stream'], ''),
            'is_streaming' => true,
        ]);

        $watcher->beforeSend($before);
        $watcher->afterSend($after);

        $this->assertCount(1, Speculum::$entriesQueue);
        $this->assertSame('Empty Response', Speculum::$entriesQueue[0]->content['response']);

        $streamEvent = new ClientEvent('HttpClient.afterSendStream', $client, [
            'request' => $request,
            'adapterOptions' => [],
            'response' => new Response(
                ['HTTP/1.1 200 OK', 'Content-Type: text/event-stream'],
                $sse,
            ),
        ]);
        $watcher->afterSendStream($streamEvent);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame($sse, $entries[0]->content['response']);
        $this->assertSame(200, $entries[0]->content['response_status']);
    }

    /**
     * @return void
     */
    public function testAfterSendStreamWithoutContextIsIgnored(): void
    {
        $client = new Client();
        $request = new Request('https://api.openai.com/v1/chat', 'POST');
        $watcher = new HttpClientWatcher(['enabled' => true]);

        $streamEvent = new ClientEvent('HttpClient.afterSendStream', $client, [
            'request' => $request,
            'adapterOptions' => [],
            'response' => new Response(['HTTP/1.1 200 OK'], 'data: orphan'),
        ]);
        $watcher->afterSendStream($streamEvent);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(0, $entries);
    }
}
