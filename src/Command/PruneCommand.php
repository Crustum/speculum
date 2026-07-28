<?php
declare(strict_types=1);

namespace Crustum\Speculum\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\DateTime;
use Crustum\Speculum\Contract\PrunableRepository;
use Crustum\Speculum\Speculum;
use Override;

/**
 * Prune old Speculum entries.
 */
class PruneCommand extends Command
{
    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'speculum prune';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Prune Speculum entries older than the given hours.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription(static::getDescription())
            ->addOption('hours', [
                'short' => 'h',
                'help' => 'Hours to retain.',
                'default' => '24',
            ])
            ->addOption('keep-exceptions', [
                'boolean' => true,
                'help' => 'Keep exception entries.',
                'default' => false,
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $storage = Speculum::getRepository();
        if (!$storage instanceof PrunableRepository) {
            $io->error('Entries repository is not prunable.');

            return static::CODE_ERROR;
        }

        $hours = (int)$args->getOption('hours');
        $before = DateTime::now()->subHours($hours);
        $deleted = $storage->prune($before, (bool)$args->getOption('keep-exceptions'));
        $io->success(sprintf('Deleted %d entries.', $deleted));

        return static::CODE_SUCCESS;
    }
}
