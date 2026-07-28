<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher\Queue;

use Cake\Console\Arguments;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\JobStatus;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\Queue\QueuesadillaJobWatcher;
use Exception;
use stdClass;

/**
 * Queuesadilla soft job watcher coverage.
 */
class QueuesadillaJobWatcherTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testLifecycleRecordsPendingThenProcessed(): void
    {
        $watcher = new QueuesadillaJobWatcher(['enabled' => true]);
        $job = $this->makeJob(['App\\Job\\QsDemoJob', 'perform'], ['id' => 9], 'mail');

        $start = new stdClass();
        $start->data = ['job' => $job];

        $watcher->recordStart($start, 'default', 'mail');

        $this->assertNotEmpty(Speculum::$entriesQueue);
        $uuid = Speculum::$entriesQueue[0]->uuid;

        usleep(1000);
        $success = new stdClass();
        $success->data = ['job' => $job];

        $watcher->recordFinish($success, JobStatus::Processed);

        $this->assertNotEmpty(Speculum::$updatesQueue);
        $this->assertSame($uuid, Speculum::$updatesQueue[0]->uuid);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(EntryType::Job->value, $entries[0]->type);
        $this->assertSame('App\\Job\\QsDemoJob', $entries[0]->content['name']);
        $this->assertSame('processed', $entries[0]->content['status']);
        $this->assertSame('mail', $entries[0]->content['queue']);
        $this->assertSame(['id' => 9], $entries[0]->content['data']);
        $this->assertIsInt($entries[0]->content['duration']);
    }

    /**
     * @return void
     */
    public function testFinishPrefersLeagueEventDuration(): void
    {
        $watcher = new QueuesadillaJobWatcher(['enabled' => true]);
        $job = $this->makeJob(['App\\Job\\TimedQsJob', 'perform'], ['id' => 1], 'default');

        $start = new stdClass();
        $start->data = ['job' => $job];

        $watcher->recordStart($start, 'default', 'default');

        $success = new stdClass();
        $success->data = [
            'job' => $job,
            'duration' => 321,
        ];
        $watcher->recordFinish($success, JobStatus::Processed);

        $entries = $this->loadSpeculumEntries();
        $this->assertSame(321, $entries[0]->content['duration']);
        $this->assertSame('processed', $entries[0]->content['status']);
    }

    /**
     * @return void
     */
    public function testFinishTagsSlowWhenDurationMeetsThreshold(): void
    {
        $watcher = new QueuesadillaJobWatcher([
            'enabled' => true,
            'slow' => 200,
        ]);
        $job = $this->makeJob(['App\\Job\\SlowQsJob', 'perform'], ['id' => 2], 'default');

        $start = new stdClass();
        $start->data = ['job' => $job];

        $watcher->recordStart($start, 'default', 'default');

        $success = new stdClass();
        $success->data = [
            'job' => $job,
            'duration' => 450,
        ];
        $watcher->recordFinish($success, JobStatus::Processed);

        $this->assertNotEmpty(Speculum::$updatesQueue);
        $this->assertTrue(Speculum::$updatesQueue[0]->changes['slow']);
        $this->assertContains('slow', Speculum::$updatesQueue[0]->tagsChanges['added']);

        $entries = $this->loadSpeculumEntries();
        $this->assertTrue($entries[0]->content['slow']);
        $this->assertSame(450, $entries[0]->content['duration']);
    }

    /**
     * @return void
     */
    public function testExceptionRecordsFailedWithPayload(): void
    {
        $watcher = new QueuesadillaJobWatcher(['enabled' => true]);
        $job = $this->makeJob('App\\Job\\BoomJob', ['x' => 1]);

        $start = new stdClass();
        $start->data = ['job' => $job];

        $watcher->recordStart($start, 'default', 'default');

        $fail = new stdClass();
        $fail->data = [
            'job' => $job,
            'exception' => new Exception('queuesadilla boom'),
        ];
        $watcher->recordFinish($fail, JobStatus::Failed);

        $entries = $this->loadSpeculumEntries();
        $this->assertSame('failed', $entries[0]->content['status']);
        $this->assertSame('queuesadilla boom', $entries[0]->content['exception']['message']);
        $this->assertSame(Exception::class, $entries[0]->content['exception']['class']);
    }

    /**
     * @return void
     */
    public function testRecordPushedCreatesPendingJob(): void
    {
        $this->markSoftPluginLoaded('Josegonzalez/CakeQueuesadilla');
        $watcher = new QueuesadillaJobWatcher(['enabled' => true]);

        $watcher->recordPushed(new Event(QueuesadillaJobWatcher::JOB_PUSHED, $this, [
            'config' => 'default',
            'success' => true,
            'item' => [
                'id' => 'job-abc-123',
                'queue' => 'uploadDicomRaw',
                'class' => '\\App\\Job\\ZulucareJob::uploadDicomRaw',
                'args' => [
                    'filename' => 'demo.dcm',
                    'anonymize' => false,
                ],
            ],
        ]));

        $queued = Speculum::$entriesQueue[0];
        $this->assertSame('pending', $queued->content['status']);
        $this->assertSame('App\\Job\\ZulucareJob::uploadDicomRaw', $queued->content['name']);
        $this->assertSame('uploadDicomRaw', $queued->content['queue']);
        $this->assertSame('job-abc-123', $queued->familyHash);
        $this->assertSame(['filename' => 'demo.dcm', 'anonymize' => false], $queued->content['data']);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(EntryType::Job->value, $entries[0]->type);
        $this->assertSame('pending', $entries[0]->content['status']);
    }

    /**
     * @return void
     */
    public function testAttachToWorkerSubscribesLeagueListeners(): void
    {
        $this->markSoftPluginLoaded('Josegonzalez/CakeQueuesadilla');

        $worker = new class {
            /**
             * @var array<string, callable>
             */
            public array $listeners = [];

            /**
             * @param string $name Event name.
             * @param callable|null $listener Listener.
             * @param array<string, mixed> $options Options.
             * @return void
             */
            public function attachListener($name, $listener = null, array $options = []): void
            {
                if (is_string($name) && is_callable($listener)) {
                    $this->listeners[$name] = $listener;
                }
            }
        };

        $watcher = new QueuesadillaJobWatcher(['enabled' => true]);
        $watcher->register();

        EventManager::instance()->dispatch(new Event(
            QueuesadillaJobWatcher::WORKER_CREATED,
            $this,
            [
                'worker' => $worker,
                'engine' => new stdClass(),
                'args' => new Arguments([], ['config' => 'redis', 'queue' => 'high'], ['config', 'queue']),
            ],
        ));

        $this->assertArrayHasKey('Worker.job.start', $worker->listeners);
        $this->assertArrayHasKey('Worker.job.success', $worker->listeners);
        $this->assertArrayHasKey('Worker.job.failure', $worker->listeners);
        $this->assertArrayHasKey('Worker.job.exception', $worker->listeners);

        $job = $this->makeJob(['App\\Job\\AttachedJob', 'run'], ['ok' => true], 'high');
        $league = new class ($job) {
            /**
             * @param object $job Job.
             */
            public function __construct(public object $job)
            {
            }

            /**
             * @return array<string, mixed>
             */
            public function data(): array
            {
                return ['job' => $this->job];
            }
        };

        ($worker->listeners['Worker.job.start'])($league);
        $this->assertTrue(Speculum::isRecording());
        $this->assertNotEmpty(Speculum::$entriesQueue);

        ($worker->listeners['Worker.job.success'])($league);
        $this->assertSame([], Speculum::$entriesQueue);
        $this->assertFalse(Speculum::isRecording());

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('App\\Job\\AttachedJob', $entries[0]->content['name']);
        $this->assertSame('processed', $entries[0]->content['status']);
        $this->assertSame('redis', $entries[0]->content['connection']);
        $this->assertSame('high', $entries[0]->content['queue']);
    }

    /**
     * Build a duck-typed queuesadilla job object.
     *
     * @param array<int, string>|string $class Job class callable.
     * @param array<string, mixed> $data Job data.
     * @param string $queue Queue name.
     * @return object
     */
    protected function makeJob(array|string $class, array $data, string $queue = 'default'): object
    {
        $item = [
            'class' => $class,
            'args' => [$data],
            'queue' => $queue,
            'attempts' => 0,
        ];

        return new class ($item) {
            /**
             * @param array<string, mixed> $item Item.
             */
            public function __construct(protected array $item)
            {
            }

            /**
             * @return array<string, mixed>
             */
            public function item(): array
            {
                return $this->item;
            }

            /**
             * @param string|null $key Key.
             * @param mixed $default Default.
             * @return mixed
             */
            public function data($key = null, $default = null): mixed
            {
                if ($key === null) {
                    return $this->item['args'][0];
                }

                return $this->item['args'][0][$key] ?? $default;
            }

            /**
             * @return int
             */
            public function attempts(): int
            {
                return (int)$this->item['attempts'];
            }
        };
    }
}
