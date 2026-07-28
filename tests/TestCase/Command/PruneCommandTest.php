<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\I18n\DateTime;
use Crustum\Speculum\Command\PruneCommand;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Crustum\Speculum\Test\TestCase\TestCaseBase;

/**
 * PruneCommand tests.
 */
class PruneCommandTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testPruneCommandWillClearOldRecords(): void
    {
        $recent = IncomingEntry::make(['message' => 'recent'])
            ->type(EntryType::Log->value)
            ->batchId('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $old = IncomingEntry::make(['message' => 'old'])
            ->type(EntryType::Log->value)
            ->batchId('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb');
        $this->repository->store([$recent, $old]);

        $this->setSpeculumEntryCreated($old->uuid, DateTime::now()->subDays(2));
        $this->setSpeculumEntryCreated($recent->uuid, DateTime::now());

        $out = new StubConsoleOutput();
        $err = new StubConsoleOutput();
        $io = new ConsoleIo($out, $err);
        $command = new PruneCommand();
        $result = $command->execute(new Arguments([], ['hours' => '24'], ['hours']), $io);

        $this->assertSame(PruneCommand::CODE_SUCCESS, $result);
        $entries = $this->repository->get(EntryType::Log->value, (new EntryQueryOptions())->limit(-1));
        $this->assertCount(1, $entries);
        $this->assertSame($recent->uuid, $entries[0]->id);
        $this->assertStringContainsString('Deleted 1 entries.', implode("\n", $out->messages()));
    }

    /**
     * @return void
     */
    public function testPruneCommandCanVaryHours(): void
    {
        $entry = IncomingEntry::make(['message' => 'aged'])
            ->type(EntryType::Log->value)
            ->batchId('cccccccc-cccc-cccc-cccc-cccccccccccc');
        $this->repository->store([$entry]);

        $this->setSpeculumEntryCreated($entry->uuid, DateTime::now()->subHours(5));

        $out = new StubConsoleOutput();
        $command = new PruneCommand();
        $command->execute(new Arguments([], ['hours' => '24'], ['hours']), new ConsoleIo($out, new StubConsoleOutput()));
        $this->assertStringContainsString('Deleted 0 entries.', implode("\n", $out->messages()));

        $out2 = new StubConsoleOutput();
        $command->execute(new Arguments([], ['hours' => '4'], ['hours']), new ConsoleIo($out2, new StubConsoleOutput()));
        $this->assertStringContainsString('Deleted 1 entries.', implode("\n", $out2->messages()));
        $this->assertSame(
            [],
            $this->repository->get(EntryType::Log->value, (new EntryQueryOptions())->limit(-1)),
        );
    }
}
