<?php
declare(strict_types=1);

namespace Crustum\Speculum\Mcp;

use Crustum\Mcp\Server;
use Crustum\Speculum\Mcp\Tools\Control;
use Crustum\Speculum\Mcp\Tools\Entry;
use Crustum\Speculum\Mcp\Tools\Search;

/**
 * Speculum MCP server for runtime entry inspection.
 */
class SpeculumServer extends Server
{
    /**
     * MCP server display name.
     *
     * @var string
     */
    protected string $name = 'Cake Speculum';

    /**
     * MCP server instructions shown to clients.
     *
     * @var string
     */
    protected string $instructions = 'Inspect and control CakePHP Speculum. Prefer speculum_search then speculum_entry (expand=related|family). Use speculum_control for Monitoring tags (monitor/unmonitor) and recording pause/resume.';

    /**
     * Tools registered on this MCP server.
     *
     * @var array<int, class-string<\Crustum\Mcp\Server\Tool>>
     */
    protected array $tools = [
        Search::class,
        Entry::class,
        Control::class,
    ];
}
