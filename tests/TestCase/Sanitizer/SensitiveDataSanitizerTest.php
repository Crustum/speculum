<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Sanitizer;

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Sanitizer\HeaderSanitizer;
use Crustum\Speculum\Sanitizer\QueryBindingSanitizer;
use Crustum\Speculum\Sanitizer\RecursiveArraySanitizer;
use Crustum\Speculum\Sanitizer\SensitiveData;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\HttpClientWatcher;
use Crustum\Speculum\Watcher\QueryWatcher;
use Crustum\Speculum\Watcher\RequestWatcher;

/**
 * Centralized sanitizer coverage (security review items [2]–[5]).
 */
class SensitiveDataSanitizerTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testRecursiveArraySanitizerMatchesNestedAndGlobPatterns(): void
    {
        $sanitizer = new RecursiveArraySanitizer([
            '*password*',
            '*token*',
        ]);

        $result = $sanitizer->sanitize([
            'email' => 'a@b.c',
            'password' => 'secret',
            'user' => [
                'password' => 'nested',
                'access_token' => 'tok',
                'name' => 'Ada',
            ],
            'Auth' => [
                'User' => [
                    'password_hash' => 'hash',
                ],
            ],
        ]);

        $this->assertSame('a@b.c', $result['email']);
        $this->assertSame('(REDACTED)', $result['password']);
        $this->assertSame('(REDACTED)', $result['user']['password']);
        $this->assertSame('(REDACTED)', $result['user']['access_token']);
        $this->assertSame('Ada', $result['user']['name']);
        $this->assertSame('(REDACTED)', $result['Auth']['User']['password_hash']);
    }

    /**
     * @return void
     */
    public function testHeaderSanitizerRedactsCookieAndCsrf(): void
    {
        $result = (new HeaderSanitizer(SensitiveData::DEFAULT_HEADER_PATTERNS))->sanitize([
            'Authorization' => 'Bearer secret',
            'Cookie' => 'CAKEPHP=abc',
            'X-XSRF-TOKEN' => 'csrf',
            'Accept' => 'application/json',
        ]);

        $this->assertSame('(REDACTED)', $result['authorization']);
        $this->assertSame('(REDACTED)', $result['cookie']);
        $this->assertSame('(REDACTED)', $result['x-xsrf-token']);
        $this->assertSame('application/json', $result['accept']);
    }

    /**
     * @return void
     */
    public function testQueryBindingSanitizerCanOmitOrRedactNamedKeys(): void
    {
        $redacted = (new QueryBindingSanitizer(['*password*']))->sanitize([
            'email' => 'a@b.c',
            'password' => 'secret',
            0 => 'positional-kept',
        ]);
        $this->assertSame('(REDACTED)', $redacted['password']);
        $this->assertSame('positional-kept', $redacted[0]);

        $omitted = (new QueryBindingSanitizer([], '(REDACTED)', true))->sanitize([
            'password' => 'secret',
        ]);
        $this->assertSame([], $omitted);
    }

    /**
     * @return void
     */
    public function testQueryBindingSanitizerRedactsPositionalFromSqlColumnContext(): void
    {
        $update = (new QueryBindingSanitizer(
            ['*password*', '*token*'],
            '(REDACTED)',
            false,
            'UPDATE users SET password = ?, remember_token = ?, email = ? WHERE id = ?',
        ))->sanitize([
            0 => 'secret-hash',
            1 => 'tok',
            2 => 'a@b.c',
            3 => 42,
        ]);

        $this->assertSame('(REDACTED)', $update[0]);
        $this->assertSame('(REDACTED)', $update[1]);
        $this->assertSame('(REDACTED)', $update[2]);
        $this->assertSame('(REDACTED)', $update[3]);

        $insert = (new QueryBindingSanitizer(
            ['*password*'],
            '(REDACTED)',
            false,
            'INSERT INTO users (email, password) VALUES (?, ?)',
        ))->sanitize([
            0 => 'a@b.c',
            1 => 'plain-secret',
        ]);
        $this->assertSame('(REDACTED)', $insert[0]);
        $this->assertSame('(REDACTED)', $insert[1]);

        $cakeNamed = (new QueryBindingSanitizer(
            ['*password*', '*token*'],
            '(REDACTED)',
            false,
            'UPDATE "users" SET "password" = :c0, "api_token" = :c1 WHERE "id" = :c2',
        ))->sanitize([
            'c0' => 'secret-hash',
            'c1' => 'tok',
            'c2' => 7,
        ]);
        $this->assertSame('(REDACTED)', $cakeNamed['c0']);
        $this->assertSame('(REDACTED)', $cakeNamed['c1']);
        $this->assertSame('(REDACTED)', $cakeNamed['c2']);

        $safe = (new QueryBindingSanitizer(
            ['*password*', '*token*'],
            '(REDACTED)',
            false,
            'UPDATE users SET email = ? WHERE id = ?',
        ))->sanitize([
            0 => 'a@b.c',
            1 => 42,
        ]);
        $this->assertSame('a@b.c', $safe[0]);
        $this->assertSame(42, $safe[1]);
    }

    /**
     * @return void
     */
    public function testQueryWatcherRedactsBindingsUsingSqlContext(): void
    {
        $watcher = new QueryWatcher(['enabled' => true, 'slow' => 1000]);
        $watcher->record(
            'UPDATE users SET password = ?, email = ? WHERE id = ?',
            1.0,
            'test',
            'sqlite',
            [
                0 => 'secret',
                1 => 'a@b.c',
                2 => 1,
            ],
        );

        $bindings = $this->loadSpeculumEntries()[0]->content['bindings'];
        $this->assertSame('(REDACTED)', $bindings[0]);
        $this->assertSame('(REDACTED)', $bindings[1]);
        $this->assertSame('(REDACTED)', $bindings[2]);
    }

    /**
     * @return void
     */
    public function testRequestWatcherRedactsCookieAndNestedSessionSecrets(): void
    {
        $session = new Session();
        $session->write([
            'Auth' => [
                'User' => [
                    'email' => 'a@b.c',
                    'password' => 'hash',
                    'api_token' => 'tok',
                ],
            ],
        ]);

        $request = (new ServerRequest([
            'url' => '/account',
            'environment' => ['REQUEST_METHOD' => 'GET'],
            'session' => $session,
        ]))->withHeader('Cookie', 'CAKEPHP=session-id')
            ->withHeader('X-CSRF-Token', 'csrf-value');

        $watcher = new RequestWatcher(['enabled' => true]);
        $watcher->record($request, (new Response())->withStatus(200), microtime(true));

        $entry = $this->loadSpeculumEntries()[0];
        $this->assertSame(EntryType::Request->value, $entry->type);
        $this->assertSame('(REDACTED)', $entry->content['headers']['cookie']);
        $this->assertSame('(REDACTED)', $entry->content['headers']['x-csrf-token']);
        $this->assertSame('a@b.c', $entry->content['session']['Auth']['User']['email']);
        $this->assertSame('(REDACTED)', $entry->content['session']['Auth']['User']['password']);
        $this->assertSame('(REDACTED)', $entry->content['session']['Auth']['User']['api_token']);
    }

    /**
     * @return void
     */
    public function testHttpClientWatcherRedactsBearerAndPayloadSecrets(): void
    {
        $watcher = new HttpClientWatcher(['enabled' => true]);
        $watcher->record(
            'POST',
            'https://api.example.com/login',
            [
                'Authorization' => 'Bearer sk-live',
                'Content-Type' => 'application/json',
            ],
            [
                'email' => 'a@b.c',
                'password' => 'secret',
                'refresh_token' => 'rt',
            ],
            [
                'status' => 200,
                'headers' => ['content-type' => 'application/json'],
                'body' => '{"access_token":"at","ok":true}',
            ],
        );

        $entry = $this->loadSpeculumEntries()[0];
        $this->assertSame('(REDACTED)', $entry->content['headers']['authorization']);
        $this->assertSame('application/json', $entry->content['headers']['content-type']);
        $this->assertSame('a@b.c', $entry->content['payload']['email']);
        $this->assertSame('(REDACTED)', $entry->content['payload']['password']);
        $this->assertSame('(REDACTED)', $entry->content['payload']['refresh_token']);

        $response = $entry->content['response'];
        if (is_string($response)) {
            $decoded = json_decode($response, true);
            $this->assertIsArray($decoded);
            $response = $decoded;
        }

        $this->assertIsArray($response);
        $this->assertSame('(REDACTED)', $response['access_token']);
        $this->assertTrue($response['ok']);
    }

    /**
     * @return void
     */
    public function testQueryWatcherRedactsNamedBindingsAndHonorsOmitConfig(): void
    {
        $watcher = new QueryWatcher(['enabled' => true, 'slow' => 1000]);
        $watcher->record(
            'UPDATE users SET password = :password WHERE id = :id',
            1.0,
            'test',
            'sqlite',
            [
                'password' => 'secret',
                'id' => 1,
            ],
        );

        $entry = $this->loadSpeculumEntries()[0];
        $this->assertSame('(REDACTED)', $entry->content['bindings']['password']);
        $this->assertSame('(REDACTED)', $entry->content['bindings']['id']);

        Speculum::flushEntries();
        Configure::write('Speculum.sanitize.bindings.omit', true);

        $watcher->record(
            'UPDATE users SET password = :password WHERE id = :id',
            1.0,
            'test',
            'sqlite',
            [
                'password' => 'secret',
                'id' => 1,
            ],
        );

        $this->assertSame([], $this->loadSpeculumEntries()[0]->content['bindings']);
        Configure::write('Speculum.sanitize.bindings.omit', false);
    }

    /**
     * @return void
     */
    public function testVarDumpPatternsCoverUsersTableSecrets(): void
    {
        $result = SensitiveData::varDumpAttributes([
            'username' => 'admiral',
            'email' => 'admiral@example.com',
            'password' => 'hash',
            'token' => 't',
            'token_expires' => '2030-01-01',
            'api_token' => 'api',
            'secret' => 's',
            'secret_verified' => true,
            'redmine_api_key' => 'rm',
            'role' => 'admin',
        ]);

        $this->assertSame('admiral', $result['username']);
        $this->assertSame('admiral@example.com', $result['email']);
        $this->assertSame('admin', $result['role']);
        $this->assertSame('(REDACTED)', $result['password']);
        $this->assertSame('(REDACTED)', $result['token']);
        $this->assertSame('(REDACTED)', $result['token_expires']);
        $this->assertSame('(REDACTED)', $result['api_token']);
        $this->assertSame('(REDACTED)', $result['secret']);
        $this->assertSame('(REDACTED)', $result['secret_verified']);
        $this->assertSame('(REDACTED)', $result['redmine_api_key']);
    }
}
