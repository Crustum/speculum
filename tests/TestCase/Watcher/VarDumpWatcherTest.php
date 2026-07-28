<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\Core\Configure;
use Cake\ORM\Entity;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Sanitizer\AbstractPatternSanitizer;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\VarDumpWatcher;
use stdClass;

/**
 * VarDump watcher tests.
 */
class VarDumpWatcherTest extends TestCaseBase
{
    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        VarDumpWatcher::resetInstance();
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testVarDumpWatcherCapturesWithoutOutput(): void
    {
        $watcher = new VarDumpWatcher(['enabled' => true]);
        $watcher->register();

        ob_start();
        dump(['hello' => 'world']);
        $output = ob_get_clean();

        $this->assertSame('', $output);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(EntryType::VarDump->value, $entries[0]->type);
        $this->assertSame('array', $entries[0]->content['summary']);
        $this->assertNotEmpty($entries[0]->content['file']);
        $this->assertIsInt($entries[0]->content['line']);
        $this->assertStringContainsString('hello', (string)$entries[0]->content['vardump']);
        $this->assertStringContainsString('world', (string)$entries[0]->content['vardump']);
    }

    /**
     * @return void
     */
    public function testSummaryForObject(): void
    {
        $watcher = new VarDumpWatcher(['enabled' => true]);
        $watcher->register();

        Speculum::varDump(new stdClass());

        $entries = $this->loadSpeculumEntries();
        $this->assertSame('stdClass', $entries[0]->content['summary']);
        $this->assertNotEmpty($entries[0]->content['file']);
        $this->assertTrue(
            str_contains((string)$entries[0]->content['file'], 'VarDumpWatcherTest.php'),
        );
        $this->assertTrue(
            str_contains((string)$entries[0]->content['file'], DIRECTORY_SEPARATOR)
            || str_starts_with((string)$entries[0]->content['file'], '/'),
            'VarDump file should be an absolute path',
        );
        $this->assertIsInt($entries[0]->content['line']);
    }

    /**
     * @return void
     */
    public function testSpeculumVarDumpRecordsOneEntryForMultipleValues(): void
    {
        $watcher = new VarDumpWatcher(['enabled' => true]);
        $watcher->register();

        Speculum::varDump(['a' => 1], 'two');

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(EntryType::VarDump->value, $entries[0]->type);
        $this->assertSame('array, string', $entries[0]->content['summary']);
        $this->assertCount(2, $entries[0]->content['vardumps']);
        $this->assertStringContainsString('VarDumpWatcherTest.php', (string)$entries[0]->content['file']);
    }

    /**
     * @return void
     */
    public function testSpeculumVarDumpNoopsWithoutWatcher(): void
    {
        VarDumpWatcher::resetInstance();
        Speculum::varDump('ignored');

        $this->assertSame([], Speculum::$entriesQueue);
    }

    /**
     * @return void
     */
    public function testDoesNotRecordWhenNotRecording(): void
    {
        $watcher = new VarDumpWatcher(['enabled' => true]);
        $watcher->register();

        Speculum::stopRecording();
        dump('silent');

        $this->assertSame([], Speculum::$entriesQueue);
    }

    /**
     * @return void
     */
    public function testAssignsEntryPointOnStore(): void
    {
        $watcher = new VarDumpWatcher(['enabled' => true]);
        $watcher->register();

        Speculum::recordEntry(EntryType::Request, IncomingEntry::make([
            'uri' => '/orders/1',
            'method' => 'GET',
            'response_status' => 200,
        ]));
        dump('payload');

        Speculum::store($this->repository);

        $entries = $this->repository->get(EntryType::VarDump->value, new EntryQueryOptions());
        $this->assertCount(1, $entries);
        $this->assertSame('GET /orders/1', $entries[0]->content['entry_point_description'] ?? null);
        $this->assertSame(EntryType::Request->value, $entries[0]->content['entry_point_type'] ?? null);
    }

    /**
     * @return void
     */
    public function testAvailableWatchersRespectsVarDumpConfig(): void
    {
        Configure::write('Speculum.watchers.' . VarDumpWatcher::class, false);
        $this->assertNotContains('vardumps', WatcherRegistry::availableWatchers());

        Configure::write('Speculum.watchers.' . VarDumpWatcher::class, [
            'enabled' => false,
        ]);
        $this->assertNotContains('vardumps', WatcherRegistry::availableWatchers());

        Configure::write('Speculum.watchers.' . VarDumpWatcher::class, [
            'enabled' => true,
        ]);
        $this->assertContains('vardumps', WatcherRegistry::availableWatchers());
    }

    /**
     * @return void
     */
    public function testRedactsUserEntitySecretsInDumpHtml(): void
    {
        $watcher = new VarDumpWatcher(['enabled' => true]);
        $watcher->register();

        $user = new Entity([
            'username' => 'admiral',
            'email' => 'admiral@example.com',
            'password' => '$2y$12$llAAFrzaejh0hiHedemB2OJIofraHqLetJby./xbkVDXJzBlYfXUq',
            'api_token' => 'secret-api-token',
            'token' => 'reset-token',
            'secret' => 'totp-secret',
            'redmine_api_key' => 'rm-key',
        ]);

        Speculum::varDump($user);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $html = (string)$entries[0]->content['vardump'];
        $this->assertStringContainsString('admiral', $html);
        $this->assertStringContainsString('admiral@example.com', $html);
        $this->assertStringContainsString(AbstractPatternSanitizer::DEFAULT_REPLACEMENT, $html);
        $this->assertStringNotContainsString('$2y$12$llAAFrzaejh0hiHedemB2OJIofraHqLetJby./xbkVDXJzBlYfXUq', $html);
        $this->assertStringNotContainsString('secret-api-token', $html);
        $this->assertStringNotContainsString('reset-token', $html);
        $this->assertStringNotContainsString('totp-secret', $html);
        $this->assertStringNotContainsString('rm-key', $html);
    }

    /**
     * @return void
     */
    public function testRedactsSensitiveKeysInArrayDump(): void
    {
        $watcher = new VarDumpWatcher(['enabled' => true]);
        $watcher->register();

        Speculum::varDump([
            'email' => 'a@b.c',
            'password' => 'plain-secret-password',
            'api_token' => 'unique-api-token-value',
        ]);

        $html = (string)$this->loadSpeculumEntries()[0]->content['vardump'];
        $this->assertStringContainsString('a@b.c', $html);
        $this->assertStringContainsString(AbstractPatternSanitizer::DEFAULT_REPLACEMENT, $html);
        $this->assertStringNotContainsString('plain-secret-password', $html);
        $this->assertStringNotContainsString('unique-api-token-value', $html);
    }
}
