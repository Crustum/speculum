<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Console;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Crustum\Speculum\Command\ClearCommand;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Crustum\Speculum\Test\TestCase\TestCaseBase;

/**
 * ClearCommand tests.
 */
class ClearCommandTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testClearCommandWillDeleteAllEntries(): void
    {
        $entry = IncomingEntry::make([
            'uri' => '/demo',
            'method' => 'GET',
            'response_status' => 200,
        ])->type(EntryType::Request->value)->batchId('11111111-1111-1111-1111-111111111111');
        $this->repository->store([$entry]);
        $this->repository->monitor(['one', 'two']);

        $out = new StubConsoleOutput();
        $err = new StubConsoleOutput();
        $io = new ConsoleIo($out, $err);
        $command = new ClearCommand();
        $result = $command->execute(new Arguments([], [], []), $io);

        $this->assertSame(ClearCommand::CODE_SUCCESS, $result);
        $this->assertSame(
            [],
            $this->repository->get(null, (new EntryQueryOptions())->limit(-1)),
        );
        $this->assertSame([], $this->repository->monitoring());
    }
}
