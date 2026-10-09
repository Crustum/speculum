<?php
declare(strict_types=1);

namespace Crustum\Speculum\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Crustum\Speculum\Command\Trait\ConsoleFormattingTrait;
use Crustum\Speculum\Entry\Inspection\EntryDetail;
use Crustum\Speculum\Entry\Inspection\EntryInspectionException;
use Crustum\Speculum\Speculum;
use Override;

/**
 * Show a Speculum entry with full batch context.
 */
class ShowCommand extends Command
{
    use ConsoleFormattingTrait;

    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'speculum show';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Show a Speculum entry with full batch context.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription(static::getDescription())
            ->addArgument('id', [
                'help' => 'Entry UUID, short prefix, "latest", or "latest:{type}".',
                'required' => true,
            ])
            ->addOption('type', ['help' => 'Filter batch entries to specific type(s), comma-separated.'])
            ->addOption('full', [
                'boolean' => true,
                'default' => false,
                'help' => 'Do not truncate details.',
            ])
            ->addOption('json', [
                'boolean' => true,
                'default' => false,
                'help' => 'Output the entry and its batch as JSON.',
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $result = Speculum::withoutRecording(function () use ($args, $io): int {
            $detail = new EntryDetail(Speculum::getRepository());
            $id = (string)$args->getArgument('id');

            try {
                $payload = $detail->show(
                    $id,
                    $this->stringOrNull($args->getOption('type')),
                    (bool)$args->getOption('full'),
                );
            } catch (EntryInspectionException $entryInspectionException) {
                $io->error($entryInspectionException->getMessage());

                return static::CODE_ERROR;
            }

            if ($args->getOption('json')) {
                $io->out($this->jsonBlock([
                    'entry' => $payload['entry'],
                    'batch' => $payload['batch'],
                ]));

                return static::CODE_SUCCESS;
            }

            $this->renderDetail(
                $io,
                $payload['detail'],
                $payload['createdAt'],
                (string)($payload['entry']['content']['hostname'] ?? ''),
                (bool)$args->getOption('full'),
            );
            $this->renderBatchGroups(
                $io,
                $payload['batchGroups'],
                $payload['requestedTypes'],
                $payload['batchId'],
            );

            return static::CODE_SUCCESS;
        });

        return is_int($result) ? $result : static::CODE_ERROR;
    }

    /**
     * Cast an option value to string or null.
     *
     * @param mixed $value Raw value.
     * @return string|null
     */
    protected function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string)$value;
    }
}
