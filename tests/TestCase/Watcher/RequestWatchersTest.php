<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\RequestWatcher;
use Laminas\Diactoros\CallbackStream;

/**
 * Request watcher tests.
 */
class RequestWatchersTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testRequestWatcherRegistersRequests(): void
    {
        $watcher = new RequestWatcher(['enabled' => true]);
        $request = new ServerRequest([
            'url' => '/emails',
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]);
        $response = (new Response())
            ->withStatus(200)
            ->withType('application/json')
            ->withStringBody(json_encode(['email' => 'themsaid@cakephp.org'], JSON_THROW_ON_ERROR));

        $watcher->record($request, $response, microtime(true) - 0.01);

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Request->value, $entry->type);
        $this->assertSame('GET', $entry->content['method']);
        $this->assertSame(200, $entry->content['response_status']);
        $this->assertSame('/emails', $entry->content['uri']);
    }

    /**
     * @return void
     */
    public function testRequestWatcherTagsSlowWhenDurationMeetsThreshold(): void
    {
        $watcher = new RequestWatcher([
            'enabled' => true,
            'slow' => 50,
        ]);
        $request = new ServerRequest([
            'url' => '/slow',
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]);
        $response = (new Response())->withStatus(200)->withStringBody('ok');

        $watcher->record($request, $response, microtime(true) - 0.2);

        $this->assertTrue(Speculum::$entriesQueue[0]->content['slow']);
        $this->assertContains('slow', Speculum::$entriesQueue[0]->tags);
        $this->assertGreaterThanOrEqual(50, Speculum::$entriesQueue[0]->content['duration']);
    }

    /**
     * @return void
     */
    public function testRequestWatcherRegisters404(): void
    {
        $watcher = new RequestWatcher(['enabled' => true]);
        $request = new ServerRequest([
            'url' => '/whatever',
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]);
        $response = (new Response())->withStatus(404)->withStringBody('Not Found');

        $watcher->record($request, $response, microtime(true));

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Request->value, $entry->type);
        $this->assertSame('GET', $entry->content['method']);
        $this->assertSame(404, $entry->content['response_status']);
        $this->assertSame('/whatever', $entry->content['uri']);
    }

    /**
     * @return void
     */
    public function testRequestWatcherHidesPassword(): void
    {
        $watcher = new RequestWatcher(['enabled' => true]);
        $request = new ServerRequest([
            'url' => '/auth',
            'environment' => ['REQUEST_METHOD' => 'POST'],
            'post' => [
                'email' => 'speculum@cakephp.org',
                'password' => 'secret',
                'password_confirmation' => 'secret',
            ],
        ]);
        $response = (new Response())->withStatus(200)->withStringBody('success');

        $watcher->record($request, $response, microtime(true));

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Request->value, $entry->type);
        $this->assertSame('POST', $entry->content['method']);
        $this->assertSame('speculum@cakephp.org', $entry->content['payload']['email']);
        $this->assertSame('(REDACTED)', $entry->content['payload']['password']);
        $this->assertSame('(REDACTED)', $entry->content['payload']['password_confirmation']);
    }

    /**
     * @return void
     */
    public function testRequestWatcherHidesAuthorization(): void
    {
        $watcher = new RequestWatcher(['enabled' => true]);
        $request = (new ServerRequest([
            'url' => '/dashboard',
            'environment' => ['REQUEST_METHOD' => 'POST'],
        ]))->withHeader('Authorization', 'Basic YWxhZGRpbjpvcGVuc2VzYW1l')
            ->withHeader('Content-Type', 'application/json');
        $response = (new Response())->withStatus(200)->withStringBody('success');

        $watcher->record($request, $response, microtime(true));

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Request->value, $entry->type);
        $this->assertSame('(REDACTED)', $entry->content['headers']['authorization']);
        $this->assertSame('application/json', $entry->content['headers']['content-type']);
    }

    /**
     * @return void
     */
    public function testRequestWatcherHidesPhpAuthPw(): void
    {
        $watcher = new RequestWatcher(['enabled' => true]);
        $request = (new ServerRequest([
            'url' => '/dashboard',
            'environment' => ['REQUEST_METHOD' => 'POST'],
        ]))->withHeader('php-auth-pw', 'secret');
        $response = (new Response())->withStatus(200)->withStringBody('success');

        $watcher->record($request, $response, microtime(true));

        $entries = $this->loadSpeculumEntries();
        $this->assertSame('(REDACTED)', $entries[0]->content['headers']['php-auth-pw']);
    }

    /**
     * @return void
     */
    public function testRequestWatcherPlainTextResponse(): void
    {
        $watcher = new RequestWatcher(['enabled' => true]);
        $request = new ServerRequest([
            'url' => '/fake-plain-text',
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]);
        $response = (new Response())
            ->withStatus(200)
            ->withType('text/plain')
            ->withStringBody('plain speculum response');

        $watcher->record($request, $response, microtime(true));

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Request->value, $entry->type);
        $this->assertSame(200, $entry->content['response_status']);
        $this->assertSame('plain speculum response', $entry->content['response']);
    }

    /**
     * @return void
     */
    public function testRequestWatcherLeavesResponseBodyReadable(): void
    {
        $watcher = new RequestWatcher(['enabled' => true]);
        $request = new ServerRequest([
            'url' => '/rewind',
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]);
        $response = (new Response())
            ->withStatus(200)
            ->withType('text/html')
            ->withStringBody('<html>ok</html>');

        $watcher->record($request, $response, microtime(true));
        Speculum::store($this->repository);

        $this->assertSame('<html>ok</html>', (string)$response->getBody());
    }

    /**
     * @return void
     */
    public function testRequestWatcherRecordsParamsAndMatchedRoute(): void
    {
        $watcher = new RequestWatcher(['enabled' => true]);
        $request = new ServerRequest([
            'url' => '/users/edit/5',
            'environment' => ['REQUEST_METHOD' => 'GET'],
            'params' => [
                'controller' => 'Users',
                'action' => 'edit',
                'pass' => ['5'],
                'plugin' => null,
                'prefix' => 'Admin',
                '_matchedRoute' => '/{prefix}/{controller}/{action}/*',
                '_ext' => null,
            ],
        ]);
        $response = (new Response())->withStatus(200)->withStringBody('ok');

        $watcher->record($request, $response, microtime(true));

        $entry = $this->loadSpeculumEntries()[0];

        $this->assertSame('Admin:Users:edit', $entry->content['controller_action']);
        $this->assertSame('/{prefix}/{controller}/{action}/*', $entry->content['matched_route']);
        $this->assertSame('Users', $entry->content['params']['controller']);
        $this->assertSame('edit', $entry->content['params']['action']);
        $this->assertSame(['5'], $entry->content['params']['pass']);
        $this->assertSame('Admin', $entry->content['params']['prefix']);
        $this->assertSame('/{prefix}/{controller}/{action}/*', $entry->content['params']['_matchedRoute']);
    }

    /**
     * @return void
     */
    public function testRequestWatcherKeepsQueryWhenPayloadPresent(): void
    {
        $watcher = new RequestWatcher(['enabled' => true]);
        $request = new ServerRequest([
            'url' => '/users/edit/5?utm=campaign',
            'environment' => ['REQUEST_METHOD' => 'POST'],
            'query' => ['utm' => 'campaign'],
            'post' => [
                'email' => 'speculum@cakephp.org',
                'password' => 'secret',
            ],
            'params' => [
                'controller' => 'Users',
                'action' => 'edit',
                '_matchedRoute' => '/{controller}/{action}/*',
            ],
        ]);
        $response = (new Response())->withStatus(200)->withStringBody('ok');

        $watcher->record($request, $response, microtime(true));

        $entry = $this->loadSpeculumEntries()[0];

        $this->assertSame('speculum@cakephp.org', $entry->content['payload']['email']);
        $this->assertSame('(REDACTED)', $entry->content['payload']['password']);
        $this->assertSame('campaign', $entry->content['query']['utm']);
        $this->assertArrayNotHasKey('utm', $entry->content['payload']);
    }

    /**
     * @return void
     */
    public function testRequestWatcherRecordsSession(): void
    {
        $watcher = new RequestWatcher(['enabled' => true]);
        $session = new Session();
        $session->write('Auth', ['id' => 7, 'email' => 'user@example.com']);
        $session->write('Flash.flash', [['message' => 'Saved', 'key' => 'flash']]);
        $session->write('password', 'should-hide');

        $request = new ServerRequest([
            'url' => '/dashboard',
            'environment' => ['REQUEST_METHOD' => 'GET'],
            'session' => $session,
        ]);
        $response = (new Response())->withStatus(200)->withStringBody('ok');

        $watcher->record($request, $response, microtime(true));

        $entry = $this->loadSpeculumEntries()[0];

        $this->assertSame(7, $entry->content['session']['Auth']['id']);
        $this->assertSame('user@example.com', $entry->content['session']['Auth']['email']);
        $this->assertSame('Saved', $entry->content['session']['Flash']['flash'][0]['message']);
        $this->assertSame('(REDACTED)', $entry->content['session']['password']);
    }

    /**
     * @return void
     */
    public function testRequestWatcherFormatsControllerActionAsCakeHandler(): void
    {
        $watcher = new RequestWatcher(['enabled' => true]);
        $request = new ServerRequest([
            'url' => '/projects/by-users',
            'environment' => ['REQUEST_METHOD' => 'GET'],
            'params' => [
                'controller' => 'Projects',
                'action' => 'byUsers',
                'plugin' => null,
            ],
        ]);
        $response = (new Response())->withStatus(200)->withStringBody('ok');

        $watcher->record($request, $response, microtime(true));

        $this->assertSame(
            'Projects:byUsers',
            $this->loadSpeculumEntries()[0]->content['controller_action'],
        );
    }

    /**
     * @return void
     */
    public function testRequestWatcherFormatsPluginPrefixDashedController(): void
    {
        $watcher = new RequestWatcher(['enabled' => true]);
        $request = new ServerRequest([
            'url' => '/admin/blog/post-tags/view/1',
            'environment' => ['REQUEST_METHOD' => 'GET'],
            'params' => [
                'controller' => 'post-tags',
                'action' => 'view',
                'plugin' => 'Blog',
                'prefix' => 'Admin',
            ],
        ]);
        $response = (new Response())->withStatus(200)->withStringBody('ok');

        $watcher->record($request, $response, microtime(true));

        $this->assertSame(
            'Admin:Blog.PostTags:view',
            $this->loadSpeculumEntries()[0]->content['controller_action'],
        );
    }

    /**
     * @return void
     */
    public function testRequestWatcherIgnoreSkipsMatchingRoute(): void
    {
        $watcher = new RequestWatcher([
            'enabled' => true,
            'ignore' => [
                ['plugin' => 'Crustum/Speculum'],
                ['controller' => 'DebugKit'],
                ['controller' => '*', 'action' => 'login'],
            ],
        ]);

        $spec = new ServerRequest([
            'url' => '/speculum/x',
            'environment' => ['REQUEST_METHOD' => 'GET'],
            'params' => [
                'plugin' => 'Crustum/Speculum',
                'controller' => 'EntryResources',
                'action' => 'view',
            ],
        ]);
        $response = (new Response())->withStatus(200)->withStringBody('ok');
        $watcher->record($spec, $response, microtime(true));
        $this->assertCount(0, Speculum::$entriesQueue);

        $login = new ServerRequest([
            'url' => '/admin/users/login',
            'environment' => ['REQUEST_METHOD' => 'GET'],
            'params' => [
                'prefix' => 'admin',
                'controller' => 'Users',
                'action' => 'login',
            ],
        ]);
        $watcher->record($login, $response, microtime(true));
        $this->assertCount(0, Speculum::$entriesQueue);

        $other = new ServerRequest([
            'url' => '/articles',
            'environment' => ['REQUEST_METHOD' => 'GET'],
            'params' => [
                'controller' => 'Articles',
                'action' => 'index',
            ],
        ]);
        $watcher->record($other, $response, microtime(true));
        $this->assertCount(1, Speculum::$entriesQueue);
    }

    /**
     * @return void
     */
    public function testStreamableResponseSkipsBodyAndPreservesStreamByDefault(): void
    {
        $sse = "data: one\n\ndata: two\n\n";
        $watcher = new RequestWatcher(['enabled' => true]);
        $request = new ServerRequest([
            'url' => '/api/stream',
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]);
        $response = (new Response())
            ->withStatus(200)
            ->withType('application/x-ndjson')
            ->withBody(new CallbackStream(static fn(): string => $sse));

        $watcher->record($request, $response, microtime(true));

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('Streaming Response', $entries[0]->content['response']);
        $this->assertTrue($entries[0]->content['is_streamable']);
        $this->assertSame(200, $entries[0]->content['response_status']);
        $this->assertSame('/api/stream', $entries[0]->content['uri']);

        // The one-shot callback stream must still be intact for the client.
        $this->assertSame($sse, (string)$response->getBody());
    }

    /**
     * @return void
     */
    public function testStreamableResponseRecordsBodyWhenIgnoreStreamableDisabled(): void
    {
        $sse = "data: one\n\ndata: two\n\n";
        $watcher = new RequestWatcher([
            'enabled' => true,
            'ignore_streamable' => false,
        ]);
        $request = new ServerRequest([
            'url' => '/api/stream',
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]);
        $response = (new Response())
            ->withStatus(200)
            ->withType('application/x-ndjson')
            ->withBody(new CallbackStream(static fn(): string => $sse));

        $watcher->record($request, $response, microtime(true));

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame($sse, $entries[0]->content['response']);
        $this->assertTrue($entries[0]->content['is_streamable']);
    }

    /**
     * @return void
     */
    public function testNonStreamableResponseHasNoStreamableFlag(): void
    {
        $watcher = new RequestWatcher(['enabled' => true]);
        $request = new ServerRequest([
            'url' => '/api/data',
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]);
        $response = (new Response())
            ->withStatus(200)
            ->withType('application/json')
            ->withStringBody('{"ok":true}');

        $watcher->record($request, $response, microtime(true));

        $this->assertArrayNotHasKey('is_streamable', Speculum::$entriesQueue[0]->content);
    }
}
