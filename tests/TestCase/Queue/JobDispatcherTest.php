<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Queue;

use Cake\Core\Configure;
use Crustum\Speculum\Queue\CakeQueueJobDispatcher;
use Crustum\Speculum\Queue\JobDispatcher;
use Crustum\Speculum\Queue\NullJobDispatcher;
use Crustum\Speculum\Test\TestCase\TestCaseBase;

/**
 * JobDispatcher resolution tests.
 */
class JobDispatcherTest extends TestCaseBase
{
    /**
     * @return void
     */
    protected function tearDown(): void
    {
        JobDispatcher::setInstance(null);
        Configure::delete('Speculum.queue.transport');
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testResolveAutoPrefersCakeQueueWhenAvailable(): void
    {
        Configure::write('Speculum.queue.transport', 'auto');
        $dispatcher = JobDispatcher::resolve();
        $this->assertInstanceOf(CakeQueueJobDispatcher::class, $dispatcher);
        $this->assertTrue($dispatcher->isAvailable());
    }

    /**
     * @return void
     */
    public function testResolveExplicitCakephp(): void
    {
        Configure::write('Speculum.queue.transport', 'cakephp');
        $this->assertInstanceOf(CakeQueueJobDispatcher::class, JobDispatcher::resolve());
    }

    /**
     * @return void
     */
    public function testSetInstanceOverridesResolve(): void
    {
        $null = new NullJobDispatcher();
        JobDispatcher::setInstance($null);
        $this->assertSame($null, JobDispatcher::resolve());
    }
}
