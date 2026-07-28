<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher\Queue;

use Cake\Event\Event;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\JobStatus;
use Crustum\Speculum\Recording\WorkerFlushPolicy;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\Queue\DereuromarkJobWatcher;
use Exception;
use Queue\Model\Entity\QueuedJob;
use stdClass;

/**
 * Dereuromark soft job watcher coverage.
 */
class DereuromarkJobWatcherTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testRecordCreatedCreatesPendingJob(): void
    {
        $watcher = new DereuromarkJobWatcher(['enabled' => true]);
        $job = new QueuedJob([
            'id' => 42,
            'job_task' => 'App.Example',
            'data' => ['x' => 1],
            'attempts' => 0,
            'job_group' => 'default',
        ]);

        $watcher->recordCreated(new Event(DereuromarkJobWatcher::JOB_CREATED, $this, [
            'job' => $job,
        ]));

        $this->assertNotEmpty(Speculum::$entriesQueue);
        $entry = Speculum::$entriesQueue[0];
        $this->assertSame(EntryType::Job->value, $entry->type);
        $this->assertSame('pending', $entry->content['status']);
        $this->assertSame('App.Example', $entry->content['name']);
        $this->assertSame('42', $entry->familyHash());
        $this->assertSame(['x' => 1], $entry->content['data']);
    }

    /**
     * @return void
     */
    public function testLifecycleLinksCreatedToCompleted(): void
    {
        WorkerFlushPolicy::reset();
        $watcher = new DereuromarkJobWatcher([
            'enabled' => true,
            'slow' => 1000,
        ]);

        $job = new QueuedJob([
            'id' => 7,
            'job_task' => 'Queue.Example',
            'data' => ['n' => 2],
            'attempts' => 1,
        ]);

        $watcher->recordCreated(new Event(DereuromarkJobWatcher::JOB_CREATED, $this, [
            'job' => $job,
        ]));
        Speculum::store();
        $pendingUuid = $this->loadSpeculumEntries()[0]->id;

        $startedJob = new QueuedJob([
            'id' => 7,
            'job_task' => 'Queue.Example',
            'data' => ['n' => 2],
            'attempts' => 1,
        ]);
        $watcher->recordStarted(new Event(DereuromarkJobWatcher::JOB_STARTED, $this, [
            'job' => $startedJob,
        ]));
        $this->assertCount(0, Speculum::$entriesQueue);

        $watcher->recordFinished(new Event(DereuromarkJobWatcher::JOB_COMPLETED, $this, [
            'job' => $startedJob,
        ]), JobStatus::Processed);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame($pendingUuid, $entries[0]->id);
        $this->assertSame('processed', $entries[0]->content['status']);
        $this->assertSame('7', $entries[0]->familyHash);
    }

    /**
     * @return void
     */
    public function testFailedRecordsExceptionPayload(): void
    {
        $watcher = new DereuromarkJobWatcher(['enabled' => true]);
        $job = new QueuedJob([
            'id' => 9,
            'job_task' => 'Queue.Boom',
            'data' => [],
            'attempts' => 2,
        ]);

        $watcher->recordStarted(new Event(DereuromarkJobWatcher::JOB_STARTED, $this, [
            'job' => $job,
        ]));
        $watcher->recordFinished(new Event(DereuromarkJobWatcher::JOB_FAILED, $this, [
            'job' => $job,
            'failureMessage' => 'nope',
            'exception' => new Exception('boom'),
        ]), JobStatus::Failed);

        $entries = $this->loadSpeculumEntries();
        $this->assertSame('failed', $entries[0]->content['status']);
        $this->assertSame('boom', $entries[0]->content['exception']['message']);
        $this->assertSame(Exception::class, $entries[0]->content['exception']['class']);
    }

    /**
     * @return void
     */
    public function testSlowDurationTagsUpdate(): void
    {
        $watcher = new DereuromarkJobWatcher([
            'enabled' => true,
            'slow' => 10,
        ]);
        $job = new QueuedJob([
            'id' => 3,
            'job_task' => 'Queue.Slow',
            'data' => [],
        ]);

        $watcher->recordStarted(new Event(DereuromarkJobWatcher::JOB_STARTED, $this, [
            'job' => $job,
        ]));
        usleep(15000);
        $watcher->recordFinished(new Event(DereuromarkJobWatcher::JOB_COMPLETED, $this, [
            'job' => $job,
        ]), JobStatus::Processed);

        $this->assertNotEmpty(Speculum::$updatesQueue);
        $this->assertTrue(Speculum::$updatesQueue[0]->changes['slow']);
        $this->assertContains('slow', Speculum::$updatesQueue[0]->tagsChanges['added']);
    }

    /**
     * @return void
     */
    public function testIgnoresEventsWithoutJobObject(): void
    {
        $watcher = new DereuromarkJobWatcher(['enabled' => true]);
        $watcher->recordCreated(new Event(DereuromarkJobWatcher::JOB_CREATED, $this, [
            'job' => new stdClass(),
        ]));
        $this->assertSame([], Speculum::$entriesQueue);
    }
}
