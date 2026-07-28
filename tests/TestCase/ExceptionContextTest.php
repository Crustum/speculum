<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase;

use Cake\Core\BasePlugin;
use Cake\Core\Plugin;
use Crustum\Speculum\Support\ExceptionContext;
use ErrorException;

/**
 * Exception source-context path allowlist tests.
 */
class ExceptionContextTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testGetReturnsLinesForProjectFile(): void
    {
        $file = __FILE__;
        $line = __LINE__;
        $exception = new ErrorException('in project', 0, E_ERROR, $file, $line);

        $context = ExceptionContext::get($exception);
        $this->assertNotEmpty($context);
        $this->assertArrayHasKey($line, $context);
        $this->assertStringContainsString('__LINE__', $context[$line]);
    }

    /**
     * @return void
     */
    public function testGetIgnoresPathsOutsideProjectRoot(): void
    {
        $outside = DIRECTORY_SEPARATOR === '\\'
            ? 'C:\\Windows\\win.ini'
            : '/etc/hosts';

        if (!is_readable($outside)) {
            $this->markTestSkipped('No readable outside-project file available on this host.');
        }

        $exception = new ErrorException('outside', 0, E_ERROR, $outside, 1);
        $this->assertSame([], ExceptionContext::get($exception));
    }

    /**
     * @return void
     */
    public function testGetHandlesEvalContext(): void
    {
        $exception = new ErrorException('eval', 0, E_ERROR, "eval()'d code", 3);
        $this->assertSame([3 => "eval()'d code"], ExceptionContext::get($exception));
    }

    /**
     * @return void
     */
    public function testGetReturnsLinesForLoadedPluginOutsideRoot(): void
    {
        $pluginRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'speculum_exception_ctx_plugin';
        $srcDir = $pluginRoot . DIRECTORY_SEPARATOR . 'src';
        if (!is_dir($srcDir) && !mkdir($srcDir, 0777, true) && !is_dir($srcDir)) {
            $this->fail('Unable to create temporary plugin directory.');
        }

        $file = $srcDir . DIRECTORY_SEPARATOR . 'Snippet.php';
        file_put_contents($file, "<?php\n\$marker = 'plugin-outside-root';\n");

        $name = 'SpeculumCtxOutside';
        if (Plugin::getCollection()->has($name)) {
            Plugin::getCollection()->remove($name);
        }

        Plugin::getCollection()->add(new BasePlugin([
            'name' => $name,
            'path' => $pluginRoot . DIRECTORY_SEPARATOR,
        ]));
        $this->softPluginsLoaded[] = $name;

        $exception = new ErrorException('plugin file', 0, E_ERROR, $file, 2);
        $context = ExceptionContext::get($exception);

        $this->assertNotEmpty($context);
        $this->assertArrayHasKey(2, $context);
        $this->assertStringContainsString('plugin-outside-root', $context[2]);
    }
}
