<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Unit;

use Crustum\Speculum\Recording\RequestPathFilter;
use Crustum\Speculum\Test\TestCase\TestCaseBase;

/**
 * Console command approval for CLI recording.
 */
class ConsoleCommandApprovalTest extends TestCaseBase
{
    /**
     * @var list<string>|null
     */
    private ?array $originalArgv = null;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->originalArgv = $_SERVER['argv'] ?? null;
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        if ($this->originalArgv === null) {
            unset($_SERVER['argv']);
        } else {
            $_SERVER['argv'] = $this->originalArgv;
        }

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testIgnoresCakeMigrationsMigrateCommand(): void
    {
        $_SERVER['argv'] = ['bin/cake.php', 'migrations', 'migrate', '-p', 'Crustum/Speculum'];

        $this->assertFalse($this->isApproved());
    }

    /**
     * @return void
     */
    public function testIgnoresQueueWorkerCommand(): void
    {
        $_SERVER['argv'] = ['bin/cake.php', 'queue', 'worker'];

        $this->assertFalse($this->isApproved());
    }

    /**
     * @return void
     */
    public function testIgnoresSpeculumListCommand(): void
    {
        $_SERVER['argv'] = ['bin/cake.php', 'speculum', 'list'];

        $this->assertFalse($this->isApproved());
    }

    /**
     * @return void
     */
    public function testAllowsAppCommands(): void
    {
        $_SERVER['argv'] = ['bin/cake.php', 'app', 'demo'];

        $this->assertTrue($this->isApproved());
    }

    /**
     * Invoke RequestPathFilter::runningApprovedConsoleCommand().
     *
     * @return bool
     */
    protected function isApproved(): bool
    {
        return RequestPathFilter::runningApprovedConsoleCommand();
    }
}
