<?php
declare(strict_types=1);

namespace Crustum\Speculum\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Crustum\Speculum\Command\Trait\ConsoleFormattingTrait;
use Crustum\Speculum\Entry\Inspection\EntryInspectionException;
use Crustum\Speculum\Entry\Inspection\EntryListing;
use Crustum\Speculum\Speculum;
use Override;

/**
 * List Speculum entries.
 */
class ListCommand extends Command
{
    use ConsoleFormattingTrait;

    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'speculum list';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'List Speculum entries.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription(static::getDescription())
            ->addArgument('type', [
                'help' => 'Entry type value or API resource path (omit for all types).',
                'required' => false,
            ])
            ->addOption('tag', ['help' => 'Filter by tag.'])
            ->addOption('batch', ['help' => 'Filter by batch ID.'])
            ->addOption('family', ['help' => 'Filter by family hash.'])
            ->addOption('limit', [
                'short' => 'l',
                'default' => '20',
                'help' => 'Max entries to show.',
            ])
            ->addOption('before', ['help' => 'Pagination cursor (sequence ID).'])
            ->addOption('types', [
                'boolean' => true,
                'default' => false,
                'help' => 'List entry types with descriptions instead of entries.',
            ])
            ->addOption('json', [
                'boolean' => true,
                'default' => false,
                'help' => 'Output entries as JSON.',
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $result = Speculum::withoutRecording(function () use ($args, $io): int {
            $listing = new EntryListing(Speculum::getRepository());

            if ($args->getOption('types')) {
                $payload = $listing->types();

                if ($args->getOption('json')) {
                    $io->out($this->jsonBlock($payload['types']));

                    return static::CODE_SUCCESS;
                }

                $rows = [];
                foreach ($payload['types'] as $row) {
                    if ($row['resource'] === '' && $row['description'] === '') {
                        $row['description'] = '<comment>Not recorded by any watcher.</comment>';
                    }

                    $rows[] = array_values($row);
                }

                $this->renderTable($io, $payload['headers'], $rows);
                $io->info('Showing ' . count($payload['types']) . ' entry types');

                return static::CODE_SUCCESS;
            }

            try {
                $payload = $listing->list($args->getArgument('type'), [
                    'tag' => $args->getOption('tag'),
                    'batch' => $args->getOption('batch'),
                    'family' => $args->getOption('family'),
                    'limit' => $args->getOption('limit'),
                    'before' => $args->getOption('before'),
                ]);
            } catch (EntryInspectionException $entryInspectionException) {
                $io->error($entryInspectionException->getMessage());

                return static::CODE_ERROR;
            }

            if ($args->getOption('json')) {
                $io->out($this->jsonBlock($payload['entries']));

                return static::CODE_SUCCESS;
            }

            $count = $payload['pagination']['count'];
            if ($count === 0) {
                $io->warning('No entries found.');

                return static::CODE_SUCCESS;
            }

            $this->renderTable($io, $payload['headers'], $payload['rows']);

            $nextBefore = $payload['pagination']['nextBefore'];
            $footer = $nextBefore !== null ? "Use --before={$nextBefore} for next page" : 'No more entries';
            $noun = $count === 1 ? 'entry' : 'entries';
            $io->info("Showing {$count} {$noun} - {$footer}");

            return static::CODE_SUCCESS;
        });

        return is_int($result) ? $result : static::CODE_ERROR;
    }
}
