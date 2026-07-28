<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Frontend;

use Cake\Core\Configure;
use Crustum\Speculum\Frontend\Assets;
use Crustum\Speculum\Test\TestCase\TestCaseBase;

/**
 * Frontend asset path resolver tests.
 */
class AssetsTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testUrlUsesConfiguredPathAndFileNames(): void
    {
        Configure::write('Speculum.assets.path', 'builds/speculum');
        $this->assertSame('/builds/speculum/app.js', Assets::jsUrl());
        $this->assertSame([
            '/builds/speculum/styles.css',
            '/builds/speculum/app.css',
        ], Assets::cssUrls());
    }

    /**
     * @return void
     */
    public function testDiskPathUsesConfiguredDir(): void
    {
        $dir = ROOT . DS . 'webroot' . DS . 'frontend';
        Configure::write('Speculum.assets.dir', $dir);
        $this->assertSame($dir, Assets::diskPath());
        $this->assertSame($dir . DS . 'styles-dark.css', Assets::diskFile(Assets::STYLES_DARK_CSS));
    }
}
