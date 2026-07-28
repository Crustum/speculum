<?php
declare(strict_types=1);

namespace Crustum\Speculum\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Crustum\Speculum\Contract\ClearableRepository;
use Crustum\Speculum\Speculum;
use Override;

/**
 * Clear all Speculum entries.
 */
class ClearCommand extends Command
{
    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'speculum clear';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Delete all Speculum entries from storage.';
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
        $storage = Speculum::getRepository();
        if (!$storage instanceof ClearableRepository) {
            $io->error('Entries repository is not clearable.');

            return static::CODE_ERROR;
        }

        $storage->clear();
        $io->success('Speculum entries cleared.');

        return static::CODE_SUCCESS;
    }
}
