<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

/**
 * Stub provider/tool with a stable short class name for tag assertions.
 */
class AiWatcherTestProvider
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['name' => 'openai'];
    }
}
