<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Crustum\Speculum\Entry\IncomingVarDumpEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Sanitizer\VarDumpSanitizer;
use Crustum\Speculum\Speculum;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;

/**
 * Captures Symfony VarDumper output into Speculum without echoing.
 */
class VarDumpWatcher extends Watcher
{
    /**
     * Active watcher instance used by Speculum::VarDump().
     *
     * @var self|null
     */
    protected static ?self $instance = null;

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        static::$instance = $this;

        VarDumper::setHandler(function (mixed $var): void {
            $this->recordValues([$var]);
        });
    }

    /**
     * Return the registered VarDump watcher, if any.
     *
     * @return self|null
     */
    public static function instance(): ?self
    {
        return static::$instance;
    }

    /**
     * Clear the registered instance (tests).
     *
     * @return void
     */
    public static function resetInstance(): void
    {
        static::$instance = null;
        VarDumper::setHandler(null);
    }

    /**
     * Record one or more values as a single Speculum entry (no output).
     *
     * @param list<mixed> $values Values to capture.
     * @return void
     */
    public function recordValues(array $values): void
    {
        if ($values === [] || !Speculum::isRecording()) {
            return;
        }

        $summaries = [];
        $htmls = [];
        foreach ($values as $value) {
            $summaries[] = $this->summarize($value);
            $htmls[] = $this->renderHtml($value);
        }

        $caller = $this->resolveCaller();
        $content = [
            'summary' => implode(', ', $summaries),
            'file' => $caller['file'],
            'line' => $caller['line'],
            'vardumps' => $htmls,
        ];

        if (count($htmls) === 1) {
            $content['vardump'] = $htmls[0];
        }

        Speculum::recordEntry(EntryType::VarDump, IncomingVarDumpEntry::make($content));
    }

    /**
     * Record a single value (Symfony dump() handler).
     *
     * @param mixed $var Value to capture.
     * @return void
     */
    public function recordValue(mixed $var): void
    {
        $this->recordValues([$var]);
    }

    /**
     * Build a short type label for the dumped value.
     *
     * @param mixed $var Value to summarize.
     * @return string
     */
    protected function summarize(mixed $var): string
    {
        if (is_object($var)) {
            return $var::class;
        }

        if (is_array($var)) {
            return 'array';
        }

        if (is_string($var)) {
            return 'string';
        }

        if (is_int($var)) {
            return 'int';
        }

        if (is_float($var)) {
            return 'float';
        }

        if (is_bool($var)) {
            return 'bool';
        }

        if ($var === null) {
            return 'null';
        }

        if (is_resource($var)) {
            return 'resource';
        }

        return 'Value';
    }

    /**
     * Resolve the first non-Speculum / non-VarDumper caller frame.
     *
     * @return array{file: ?string, line: ?int}
     */
    protected function resolveCaller(): array
    {
        $packageSrc = str_replace('\\', '/', dirname(__DIR__));

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30) as $frame) {
            $file = $frame['file'] ?? null;
            if (!is_string($file)) {
                continue;
            }

            if ($file === '') {
                continue;
            }

            $normalized = str_replace('\\', '/', $file);
            if (str_contains($normalized, '/symfony/var-dumper/')) {
                continue;
            }

            if (str_ends_with($normalized, '/var-dumper/Resources/functions/dump.php')) {
                continue;
            }

            if (str_starts_with($normalized, $packageSrc . '/')) {
                continue;
            }

            return [
                'file' => $file,
                'line' => $frame['line'] ?? null,
            ];
        }

        return ['file' => null, 'line' => null];
    }

    /**
     * Render a value to Symfony HtmlDumper HTML.
     *
     * @param mixed $var Value to render.
     * @return string
     */
    protected function renderHtml(mixed $var): string
    {
        $cloner = new VarCloner();
        $cloner->setMaxItems((int)($this->options['max_items'] ?? 250));
        $cloner->setMaxString((int)($this->options['max_string'] ?? 5000));
        VarDumpSanitizer::configureCloner($cloner);

        $dumper = new HtmlDumper();
        $dumper->setDumpHeader('');

        $html = $dumper->dump($cloner->cloneVar(VarDumpSanitizer::prepare($var)), true);
        if (!is_string($html)) {
            return '';
        }

        $maxBytes = (int)($this->options['max_bytes'] ?? 65536);
        if ($maxBytes > 0 && strlen($html) > $maxBytes) {
            return substr($html, 0, $maxBytes) . "\n<!-- vardump truncated -->";
        }

        return $html;
    }
}
