<?php
declare(strict_types=1);

namespace Crustum\Speculum\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Crustum\Mcp\Command\McpStartCommand;
use Override;

/**
 * Starts the Cake Speculum MCP server over STDIO.
 */
class McpCommand extends Command
{
    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'speculum mcp';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Start the Cake Speculum MCP server (usually from mcp.json)';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription(static::getDescription());
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        return $this->executeCommand(McpStartCommand::class, ['cake-speculum'], $io);
    }
}
