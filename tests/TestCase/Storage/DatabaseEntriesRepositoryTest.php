<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Storage;

use Cake\I18n\DateTime;
use Cake\Utility\Text;
use Crustum\Speculum\Entry\EntryUpdate;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Entry\IncomingExceptionEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Exception;
use RuntimeException;

/**
 * DatabaseEntriesRepository store/find/update/prune/clear coverage.
 */
class DatabaseEntriesRepositoryTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testStoreAndFindEntry(): void
    {
        $entry = IncomingEntry::make([
            'uri' => '/demo',
            'method' => 'GET',
            'response_status' => 200,
        ])->type(EntryType::Request->value)->batchId('11111111-1111-1111-1111-111111111111');

        $entry->tags(['demo']);

        $this->repository->store([$entry]);

        $found = $this->repository->find($entry->uuid);
        $this->assertSame($entry->uuid, $found->id);
        $this->assertSame(EntryType::Request->value, $found->type);
        $this->assertSame('/demo', $found->content['uri']);
        $this->assertSame(['demo'], $found->jsonSerialize()['tags']);

        $list = $this->repository->get(EntryType::Request->value, (new EntryQueryOptions())->limit(10));
        $this->assertCount(1, $list);
        $this->assertSame($entry->uuid, $list[0]->id);
    }

    /**
     * @return void
     */
    public function testGetFiltersByEntryTag(): void
    {
        $tagged = IncomingEntry::make([
            'uri' => '/tagged',
            'method' => 'GET',
            'response_status' => 200,
        ])->type(EntryType::Request->value)->batchId(Text::uuid());
        $tagged->tags(['Auth:1', 'slow']);

        $other = IncomingEntry::make([
            'uri' => '/other',
            'method' => 'GET',
            'response_status' => 200,
        ])->type(EntryType::Request->value)->batchId(Text::uuid());
        $other->tags(['other']);

        $this->repository->store([$tagged, $other]);

        $byTag = $this->repository->get(
            EntryType::Request->value,
            (new EntryQueryOptions())->tag('Auth:1')->limit(10),
        );
        $this->assertCount(1, $byTag);
        $this->assertSame($tagged->uuid, $byTag[0]->id);

        $byComma = $this->repository->get(
            EntryType::Request->value,
            (new EntryQueryOptions())->tag('missing,slow')->limit(10),
        );
        $this->assertCount(1, $byComma);
        $this->assertSame($tagged->uuid, $byComma[0]->id);

        $none = $this->repository->get(
            EntryType::Request->value,
            (new EntryQueryOptions())->tag('no-such-tag')->limit(10),
        );
        $this->assertSame([], $none);
    }

    /**
     * @return void
     */
    public function testFindEntryByUuid(): void
    {
        $entry = IncomingEntry::make([
            'level' => 'info',
            'message' => 'hello',
        ])->type(EntryType::Log->value)->batchId(Text::uuid());
        $this->repository->store([$entry]);

        $result = $this->repository->find($entry->uuid)->jsonSerialize();

        $this->assertSame($entry->uuid, $result['id']);
        $this->assertSame($entry->batchId, $result['batch_id']);
        $this->assertSame(EntryType::Log->value, $result['type']);
        $this->assertSame('hello', $result['content']['message']);
    }

    /**
     * @return void
     */
    public function testUpdate(): void
    {
        $entry = IncomingEntry::make([
            'status' => 'pending',
            'name' => 'DemoJob',
        ])->type(EntryType::Job->value)->batchId(Text::uuid());
        $this->repository->store([$entry]);

        $missingUuid = Text::uuid();
        $failedUpdates = $this->repository->update([
            EntryUpdate::make($entry->uuid, EntryType::Job, ['status' => 'processed', 'foo' => 'bar']),
            EntryUpdate::make($missingUuid, EntryType::Job, ['status' => 'processed']),
        ]);

        $this->assertCount(1, $failedUpdates);
        $this->assertSame($missingUuid, $failedUpdates[0]->uuid);

        $found = $this->repository->find($entry->uuid);
        $this->assertSame('processed', $found->content['status']);
        $this->assertSame('bar', $found->content['foo']);
    }

    /**
     * @return void
     */
    public function testStorePersistsDurationColumn(): void
    {
        $withDuration = IncomingEntry::make([
            'uri' => '/slow',
            'method' => 'GET',
            'response_status' => 200,
            'duration' => 1500,
        ])->type(EntryType::Request->value)->batchId(Text::uuid());

        $withTime = IncomingEntry::make([
            'sql' => 'select 1',
            'time' => '42.6',
        ])->type(EntryType::Query->value)->batchId(Text::uuid());

        $this->repository->store([$withDuration, $withTime]);

        $entries = $this->fetchTable('Crustum/Speculum.SpeculumEntries');
        $request = $entries->findByUuid($withDuration->uuid, false);
        $query = $entries->findByUuid($withTime->uuid, false);

        $this->assertSame(1500, $request?->duration);
        $this->assertSame(43, $query?->duration);
    }

    /**
     * @return void
     */
    public function testUpdatePersistsDurationColumn(): void
    {
        $entry = IncomingEntry::make([
            'status' => 'pending',
            'name' => 'DemoJob',
        ])->type(EntryType::Job->value)->batchId(Text::uuid());
        $this->repository->store([$entry]);

        $this->repository->update([
            EntryUpdate::make($entry->uuid, EntryType::Job, [
                'status' => 'processed',
                'duration' => 250,
                'slow' => false,
            ]),
        ]);

        $row = $this->fetchTable('Crustum/Speculum.SpeculumEntries')->findByUuid($entry->uuid, false);
        $this->assertSame(250, $row?->duration);
    }

    /**
     * @return void
     */
    public function testGetFiltersByMinDurationAndOrdersByDuration(): void
    {
        $fast = IncomingEntry::make(['uri' => '/fast', 'duration' => 10])
            ->type(EntryType::Request->value)
            ->batchId(Text::uuid());
        $slow = IncomingEntry::make(['uri' => '/slow', 'duration' => 900])
            ->type(EntryType::Request->value)
            ->batchId(Text::uuid());
        $noDuration = IncomingEntry::make(['uri' => '/none'])
            ->type(EntryType::Request->value)
            ->batchId(Text::uuid());
        $this->repository->store([$fast, $slow, $noDuration]);

        $filtered = $this->repository->get(
            EntryType::Request->value,
            (new EntryQueryOptions())->minDuration(100)->limit(-1),
        );
        $this->assertCount(1, $filtered);
        $this->assertSame($slow->uuid, $filtered[0]->id);

        $ordered = $this->repository->get(
            EntryType::Request->value,
            (new EntryQueryOptions())->orderBy('duration')->orderDirection('desc')->limit(-1),
        );
        $this->assertCount(2, $ordered);
        $this->assertSame($slow->uuid, $ordered[0]->id);
        $this->assertSame($fast->uuid, $ordered[1]->id);
        $this->assertSame(900, $ordered[0]->duration);

        $page = $this->repository->get(
            EntryType::Request->value,
            (new EntryQueryOptions())
                ->orderBy('duration')
                ->orderDirection('desc')
                ->beforeSequence($ordered[0]->sequence)
                ->beforeDuration($ordered[0]->duration)
                ->limit(-1),
        );
        $this->assertCount(1, $page);
        $this->assertSame($fast->uuid, $page[0]->id);
    }

    /**
     * @return void
     */
    public function testPruneRemovesOldEntries(): void
    {
        $recent = IncomingEntry::make(['message' => 'recent'])
            ->type(EntryType::Log->value)
            ->batchId(Text::uuid());
        $old = IncomingEntry::make(['message' => 'old'])
            ->type(EntryType::Log->value)
            ->batchId(Text::uuid());
        $this->repository->store([$recent, $old]);

        $this->setSpeculumEntryCreated($old->uuid, DateTime::now()->subDays(2));

        $deleted = $this->repository->prune(DateTime::now()->subDays(1), false);
        $this->assertSame(1, $deleted);

        $entries = $this->repository->get(EntryType::Log->value, (new EntryQueryOptions())->limit(-1));
        $this->assertCount(1, $entries);
        $this->assertSame($recent->uuid, $entries[0]->id);
    }

    /**
     * @return void
     */
    public function testClearRemovesEntriesAndMonitoring(): void
    {
        $entry = IncomingEntry::make(['message' => 'x'])
            ->type(EntryType::Log->value)
            ->batchId(Text::uuid());
        $this->repository->store([$entry]);
        $this->repository->monitor(['one', 'two']);

        $this->repository->clear();

        $this->assertSame([], $this->repository->get(null, (new EntryQueryOptions())->limit(-1)));
        $this->assertSame([], $this->repository->monitoring());
    }

    /**
     * @return void
     */
    public function testMonitorTags(): void
    {
        $this->repository->monitor(['Auth:1', 'slow']);
        $this->assertTrue($this->repository->isMonitoring(['Auth:1']));
        $this->assertContains('slow', $this->repository->monitoring());
        $this->repository->stopMonitoring(['slow']);
        $this->assertFalse($this->repository->isMonitoring(['slow']));
    }

    /**
     * @return void
     */
    public function testStoreExceptionFamily(): void
    {
        $exception = new RuntimeException('boom');
        $entry = IncomingExceptionEntry::make($exception, [
            'class' => RuntimeException::class,
            'file' => __FILE__,
            'line' => __LINE__,
            'message' => 'boom',
            'context' => null,
            'trace' => [],
            'line_preview' => [],
        ])->type(EntryType::Exception->value)->batchId('22222222-2222-2222-2222-222222222222')
            ->withFamilyHash(md5(__FILE__ . __LINE__));

        $this->repository->store([$entry]);
        $found = $this->repository->find($entry->uuid);
        $this->assertSame(1, $found->content['occurrences'] ?? null);
    }

    /**
     * @return void
     */
    public function testStoreBinarySafeContent(): void
    {
        $batchId = Text::uuid();
        $exception = new Exception('message');
        $compressed = gzcompress('message');
        $this->assertNotFalse($compressed);

        $log = IncomingEntry::make(['message' => base64_encode($compressed)])
            ->batchId($batchId)
            ->type(EntryType::Log->value);
        $exc = IncomingExceptionEntry::make($exception, [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'message' => 'message',
            'context' => null,
            'trace' => [],
            'line_preview' => [],
        ])->batchId($batchId)->type(EntryType::Exception->value);

        $this->repository->store([$log, $exc]);

        $foundLog = $this->repository->find($log->uuid);
        $this->assertSame(base64_encode($compressed), $foundLog->content['message']);
        $foundExc = $this->repository->find($exc->uuid);
        $this->assertSame('message', $foundExc->content['message']);
    }

    /**
     * @return void
     */
    public function testFindEntryByShortUuidPrefix(): void
    {
        $entry = IncomingEntry::make([
            'uri' => '/short',
            'method' => 'GET',
            'response_status' => 200,
        ], 'abc12345-1111-4111-8111-111111111111')
            ->type(EntryType::Request->value)
            ->batchId(Text::uuid());
        $entry->tags(['short-tag']);

        $this->repository->store([$entry]);

        $found = $this->repository->find('abc12345');

        $this->assertSame('abc12345-1111-4111-8111-111111111111', $found->id);
        $this->assertSame(['short-tag'], $found->jsonSerialize()['tags']);
    }

    /**
     * @return void
     */
    public function testFindEntryByShortUuidPrefixReturnsLatest(): void
    {
        $old = IncomingEntry::make([
            'uri' => '/old',
            'method' => 'GET',
            'response_status' => 200,
        ], 'def56789-1111-4111-8111-111111111111')
            ->type(EntryType::Request->value)
            ->batchId(Text::uuid());
        $new = IncomingEntry::make([
            'uri' => '/new',
            'method' => 'GET',
            'response_status' => 200,
        ], 'def56789-2222-4222-8222-222222222222')
            ->type(EntryType::Request->value)
            ->batchId(Text::uuid());
        $this->repository->store([$old, $new]);

        $found = $this->repository->find('def56789');

        $this->assertSame('def56789-2222-4222-8222-222222222222', $found->id);
        $this->assertSame('/new', $found->content['uri']);
    }

    /**
     * @return void
     */
    public function testFindEntryByDashedShortPrefix(): void
    {
        $entry = IncomingEntry::make([
            'uri' => '/dashed',
            'method' => 'GET',
            'response_status' => 200,
        ], 'abc12345-1111-4111-8111-111111111111')
            ->type(EntryType::Request->value)
            ->batchId(Text::uuid());
        $this->repository->store([$entry]);

        $found = $this->repository->find('abc12345-1111');

        $this->assertSame('abc12345-1111-4111-8111-111111111111', $found->id);
    }
}
