<?php
declare(strict_types=1);

namespace Crustum\Speculum\Frontend;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Crustum\Speculum\Speculum;
use Exception;

/**
 * Variables injected into the Speculum SPA shell.
 */
final class ScriptVariables
{
    /**
     * Return variables injected into the Speculum SPA shell.
     *
     * @return array{path: mixed, timezone: mixed, recording: bool, root: string, editor: string}
     */
    public static function all(): array
    {
        $recording = true;
        try {
            $recording = !Cache::read(Speculum::PAUSE_CACHE_KEY);
        } catch (Exception) {
        }

        return [
            'path' => Configure::read('Speculum.path', 'speculum'),
            'timezone' => Configure::read('App.defaultTimezone', date_default_timezone_get() ?: 'UTC'),
            'recording' => $recording,
            'root' => defined('ROOT')
                ? str_replace('\\', '/', rtrim((string)constant('ROOT'), '/\\'))
                : '',
            'editor' => (string)Configure::read('Debugger.editor', 'phpstorm'),
        ];
    }
}
