<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase;

use Cake\Error\Debugger;
use Crustum\Speculum\Entry\EntryResult;
use Crustum\Speculum\Support\EditorLink;
use DateTimeImmutable;

/**
 * EditorLink / Debugger::editorUrl integration.
 */
class EditorLinkTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testUrlUsesCakeDebugger(): void
    {
        Debugger::setEditor('phpstorm');
        $file = '/projects/demo/src/Controller/PostsController.php';
        $url = EditorLink::url($file, 71);

        $this->assertNotNull($url);
        $this->assertStringContainsString('phpstorm://open', $url);
        $this->assertStringContainsString('line=71', $url);
        $this->assertStringContainsString('PostsController.php', $url);
    }

    /**
     * @return void
     */
    public function testEnrichContentAddsEditorUrlForFileAndTrace(): void
    {
        Debugger::setEditor('vscode');
        $file = __FILE__;
        $enriched = EditorLink::enrichContent([
            'file' => $file,
            'line' => 10,
            'trace' => [
                ['file' => $file, 'line' => 20],
            ],
        ]);

        $this->assertArrayHasKey('editor_url', $enriched);
        $this->assertStringStartsWith('vscode://file/', $enriched['editor_url']);
        $this->assertArrayHasKey('editor_url', $enriched['trace'][0]);
        $this->assertStringStartsWith('vscode://file/', $enriched['trace'][0]['editor_url']);
    }

    /**
     * @return void
     */
    public function testEntryResultJsonIncludesEditorUrl(): void
    {
        Debugger::setEditor('phpstorm');
        $entry = new EntryResult(
            'id',
            1,
            'batch',
            'vardump',
            null,
            [
                'file' => __FILE__,
                'line' => 5,
                'summary' => 'string',
            ],
            new DateTimeImmutable(),
        );

        $json = $entry->jsonSerialize();
        $this->assertArrayHasKey('editor_url', $json['content']);
        $this->assertStringContainsString('phpstorm://open', $json['content']['editor_url']);
    }
}
