<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\RequestWatcher;

/**
 * Tests for ignore_content_types matching, including charset-param content types.
 */
class IgnoreContentTypeTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testShouldIgnoreContentTypeExactMatch(): void
    {
        $watcher = new RequestWatcher([
            'ignore_content_types' => ['text/event-stream'],
        ]);

        $response = (new Response())
            ->withHeader('Content-Type', 'text/event-stream');

        $this->assertTrue($watcher->shouldIgnoreContentType($response));
    }

    /**
     * @return void
     */
    public function testShouldIgnoreContentTypeWithCharsetParam(): void
    {
        $watcher = new RequestWatcher([
            'ignore_content_types' => ['text/event-stream'],
        ]);

        $response = (new Response())
            ->withHeader('Content-Type', 'text/event-stream; charset=UTF-8');

        $this->assertTrue($watcher->shouldIgnoreContentType($response));
    }

    /**
     * @return void
     */
    public function testShouldIgnoreContentTypeWithCharsetParamCaseInsensitive(): void
    {
        $watcher = new RequestWatcher([
            'ignore_content_types' => ['text/event-stream'],
        ]);

        $response = (new Response())
            ->withHeader('Content-Type', 'Text/Event-Stream; charset=utf-8');

        $this->assertTrue($watcher->shouldIgnoreContentType($response));
    }

    /**
     * @return void
     */
    public function testShouldNotIgnoreNonMatchingContentType(): void
    {
        $watcher = new RequestWatcher([
            'ignore_content_types' => ['text/event-stream'],
        ]);

        $response = (new Response())
            ->withHeader('Content-Type', 'application/json');

        $this->assertFalse($watcher->shouldIgnoreContentType($response));
    }

    /**
     * @return void
     */
    public function testShouldNotIgnoreWhenListIsEmpty(): void
    {
        $watcher = new RequestWatcher([
            'ignore_content_types' => [],
        ]);

        $response = (new Response())
            ->withHeader('Content-Type', 'text/event-stream; charset=UTF-8');

        $this->assertFalse($watcher->shouldIgnoreContentType($response));
    }

    /**
     * @return void
     */
    public function testRecordSkipsBodyForIgnoredContentType(): void
    {
        $sseData = "data: {\"hello\":\"world\"}\n\n";
        $response = (new Response())
            ->withStatus(200)
            ->withHeader('Content-Type', 'text/event-stream; charset=UTF-8')
            ->withStringBody($sseData);

        $request = new ServerRequest([
            'environment' => [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/api/chat',
            ],
        ]);

        $watcher = new RequestWatcher([
            'enabled' => true,
            'ignore_content_types' => ['text/event-stream'],
        ]);

        $watcher->record($request, $response, microtime(true));

        $entries = $this->loadSpeculumEntries();
        $this->assertNotEmpty($entries);
        $this->assertSame('Skipped By Speculum', $entries[0]->content['response']);
    }

    /**
     * @return void
     */
    public function testRecordPreservesSseBodyForClientReadAfterRecording(): void
    {
        $sseData = "data: {\"token\":\"hello\"}\n\ndata: {\"token\":\"world\"}\n\n";
        $response = (new Response())
            ->withStatus(200)
            ->withHeader('Content-Type', 'text/event-stream; charset=UTF-8')
            ->withStringBody($sseData);

        $request = new ServerRequest([
            'environment' => [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/api/stream',
            ],
        ]);

        $watcher = new RequestWatcher([
            'enabled' => true,
            'ignore_content_types' => ['text/event-stream'],
        ]);

        $watcher->record($request, $response, microtime(true));

        $this->assertSame($sseData, (string)$response->getBody());
    }

    /**
     * @return void
     */
    public function testMultipleIgnoredContentTypes(): void
    {
        $watcher = new RequestWatcher([
            'ignore_content_types' => [
                'text/event-stream',
                'text/html',
            ],
        ]);

        $sseResponse = (new Response())
            ->withHeader('Content-Type', 'text/event-stream; charset=UTF-8');
        $htmlResponse = (new Response())
            ->withHeader('Content-Type', 'text/html; charset=UTF-8');
        $jsonResponse = (new Response())
            ->withHeader('Content-Type', 'application/json');

        $this->assertTrue($watcher->shouldIgnoreContentType($sseResponse));
        $this->assertTrue($watcher->shouldIgnoreContentType($htmlResponse));
        $this->assertFalse($watcher->shouldIgnoreContentType($jsonResponse));
    }
}
