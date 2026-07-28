<?php
declare(strict_types=1);

namespace Crustum\Speculum\Support;

use Cake\Error\Debugger;
use Throwable;

/**
 * Builds CakePHP Debugger IDE links for stored file locations.
 */
class EditorLink
{
    /**
     * Create an editor URL via Cake\Error\Debugger::editorUrl().
     *
     * Honors host `Debugger.editor` / `Debugger.editorBasePath` config.
     *
     * @param string $file Absolute (preferred) or project path.
     * @param int $line Line number (1-based).
     * @return string|null
     */
    public static function url(string $file, int $line = 1): ?string
    {
        if ($file === '') {
            return null;
        }

        try {
            return Debugger::editorUrl($file, max(1, $line));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Attach `editor_url` for content `file`/`path` and stack `trace` frames.
     *
     * @param array<string, mixed> $content Entry content.
     * @return array<string, mixed>
     */
    public static function enrichContent(array $content): array
    {
        $file = null;
        $line = 1;

        if (isset($content['file']) && is_string($content['file']) && $content['file'] !== '') {
            $file = $content['file'];
            $line = isset($content['line']) ? (int)$content['line'] : 1;
        } elseif (isset($content['path']) && is_string($content['path']) && $content['path'] !== '') {
            $file = $content['path'];
            $line = isset($content['line']) ? (int)$content['line'] : 1;
        }

        if ($file !== null) {
            $url = static::url($file, $line);
            if ($url !== null) {
                $content['editor_url'] = $url;
            }
        }

        if (isset($content['trace']) && is_array($content['trace'])) {
            $content['trace'] = array_map(static function (mixed $frame): mixed {
                if (!is_array($frame)) {
                    return $frame;
                }

                $frameFile = $frame['file'] ?? null;
                if (!is_string($frameFile) || $frameFile === '') {
                    return $frame;
                }

                $frameLine = isset($frame['line']) ? (int)$frame['line'] : 1;
                $url = static::url($frameFile, $frameLine);
                if ($url !== null) {
                    $frame['editor_url'] = $url;
                }

                return $frame;
            }, $content['trace']);
        }

        return $content;
    }
}
