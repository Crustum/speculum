<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Unit;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\RequestWatcher;

/**
 * Request watcher must not consume the response body stream.
 */
class RequestWatcherBodyRewindTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testRecordLeavesResponseBodyReadable(): void
    {
        $html = '<html><body>speculum-body-ok</body></html>';
        $response = (new Response())->withStringBody($html);
        $request = new ServerRequest([
            'environment' => [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/admin/users',
            ],
        ]);

        $watcher = new RequestWatcher(['enabled' => true, 'size_limit' => 64]);
        $watcher->record($request, $response, microtime(true));

        $this->assertSame($html, (string)$response->getBody());
        $this->assertNotEmpty(Speculum::$entriesQueue);
        $this->assertSame($html, Speculum::$entriesQueue[0]->content['response']);
    }
}
