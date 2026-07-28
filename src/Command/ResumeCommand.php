<?php
declare(strict_types=1);

namespace Crustum\Speculum\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Crustum\Speculum\Speculum;
use Override;

/**
 * Resume Speculum recording.
 */
class ResumeCommand extends Command
{
    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'speculum resume';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Resume recording of Speculum entries.';
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
        Speculum::resumeRecording();
        $io->success('Speculum recording resumed.');

        return static::CODE_SUCCESS;
    }
}
