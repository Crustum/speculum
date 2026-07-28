<?php
declare(strict_types=1);

namespace Crustum\Speculum\Frontend;

use Cake\Core\Configure;
use Cake\Core\Plugin;

/**
 * Resolves Speculum SPA Vite build URLs and disk paths.
 */
final class Assets
{
    public const JS = 'app.js';

    public const APP_CSS = 'app.css';

    public const STYLES_CSS = 'styles.css';

    public const STYLES_DARK_CSS = 'styles-dark.css';

    /**
     * Web path under host webroot (no leading/trailing slash), e.g. `speculum`.
     *
     * @return string
     */
    public static function urlPath(): string
    {
        return trim((string)Configure::read('Speculum.assets.path', 'speculum'), '/');
    }

    /**
     * Absolute URL path for a build file (leading slash).
     *
     * @param string $file File name under the build folder.
     * @return string
     */
    public static function url(string $file): string
    {
        return '/' . self::urlPath() . '/' . ltrim($file, '/');
    }

    /**
     * CSS files to link in order.
     *
     * @return list<string>
     */
    public static function cssUrls(): array
    {
        return [
            self::url(self::STYLES_CSS),
            self::url(self::APP_CSS),
        ];
    }

    /**
     * Main SPA module script URL.
     *
     * @return string
     */
    public static function jsUrl(): string
    {
        return self::url(self::JS);
    }

    /**
     * Filesystem directory containing the Vite build output.
     *
     * @return string
     */
    public static function diskPath(): string
    {
        $configured = Configure::read('Speculum.assets.dir');
        if (is_string($configured) && $configured !== '') {
            if (preg_match('#^[a-zA-Z]:[\\\\/]#', $configured) === 1 || str_starts_with($configured, '/')) {
                return rtrim($configured, '/\\');
            }

            $root = defined('ROOT') ? (string)constant('ROOT') : dirname(__DIR__, 3);

            return rtrim($root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configured), '/\\');
        }

        if (defined('WWW_ROOT')) {
            $web = rtrim(constant('WWW_ROOT'), '/\\') . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, self::urlPath());
            if (is_dir($web)) {
                return $web;
            }
        }

        return rtrim(Plugin::path('Crustum/Speculum'), '/\\')
            . DIRECTORY_SEPARATOR . 'webroot'
            . DIRECTORY_SEPARATOR . 'frontend';
    }

    /**
     * Absolute path to a build file on disk.
     *
     * @param string $file File name.
     * @return string
     */
    public static function diskFile(string $file): string
    {
        return self::diskPath() . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file), '/\\');
    }
}
