<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\ORM\Entity;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\ModelWatcher;
use DebugKit\Model\Entity\Panel;
use stdClass;

/**
 * Model watcher tests.
 */
class ModelWatcherTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testModelWatcherIgnoresDebugKitPanelEntity(): void
    {
        $watcher = new ModelWatcher([
            'enabled' => true,
            'ignore_namespaces' => ['DebugKit\\'],
        ]);
        $entity = new Panel(['title' => 'SqlLog'], ['markNew' => true]);
        $entity->setDirty('title', true);

        $watcher->recordAction('Model.afterSave', new stdClass(), $entity);

        $this->assertSame([], Speculum::$entriesQueue);
        $this->assertSame([], $this->loadSpeculumEntries());
    }

    /**
     * @return void
     */
    public function testModelWatcherRegistersEntry(): void
    {
        $watcher = new ModelWatcher(['enabled' => true]);
        $entity = new Entity(['name' => 'Speculum'], ['markNew' => true, 'source' => 'Users']);
        $entity->setDirty('name', true);

        $watcher->recordAction('Model.afterSave', new stdClass(), $entity);

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Model->value, $entry->type);
        $this->assertSame('created', $entry->content['action']);
        $this->assertSame(Entity::class, $entry->content['model']);
        $this->assertSame(['name' => 'Speculum'], $entry->content['changes']);
    }

    /**
     * @return void
     */
    public function testModelWatcherHidesSensitiveChanges(): void
    {
        Speculum::hideModelAttributes(['api_key']);

        $watcher = new ModelWatcher(['enabled' => true]);
        $entity = new Entity([
            'name' => 'Admiral',
            'password' => 'secret-hash',
            'api_key' => 'live-key',
            'token' => 'should-be-pattern-redacted',
            'nickname' => 'still-visible-via-json-hidden-only',
        ], ['markNew' => false, 'source' => 'Users']);
        $entity->setHidden(['nickname'], true);
        $entity->setDirty('name', true);
        $entity->setDirty('password', true);
        $entity->setDirty('api_key', true);
        $entity->setDirty('token', true);
        $entity->setDirty('nickname', true);

        $watcher->recordAction('Model.afterSave', new stdClass(), $entity);

        $changes = $this->loadSpeculumEntries()[0]->content['changes'];
        $this->assertSame('Admiral', $changes['name']);
        $this->assertSame('(REDACTED)', $changes['password']);
        $this->assertSame('(REDACTED)', $changes['api_key']);
        $this->assertSame('(REDACTED)', $changes['token']);
        $this->assertSame('still-visible-via-json-hidden-only', $changes['nickname']);
    }

    /**
     * @return void
     */
    public function testModelWatcherRegistersDeleteEntry(): void
    {
        $watcher = new ModelWatcher(['enabled' => true]);
        $entity = new Entity(['id' => 1], ['source' => 'Users']);

        $watcher->recordAction('Model.afterDelete', new stdClass(), $entity);

        $entries = $this->loadSpeculumEntries();
        $this->assertSame('deleted', $entries[0]->content['action']);
        $this->assertSame(EntryType::Model->value, $entries[0]->type);
    }

    /**
     * @return void
     */
    public function testModelWatcherRegistersHydrationEntry(): void
    {
        $watcher = new ModelWatcher([
            'enabled' => true,
            'hydrations' => true,
        ]);

        Speculum::stopRecording();
        Speculum::startRecording(false);

        $watcher->recordHydrations(new stdClass(), new Entity(['id' => 1]));
        $watcher->recordHydrations(new stdClass(), new Entity(['id' => 2]));
        $watcher->recordHydrations(new stdClass(), new Entity(['id' => 3]));

        $this->assertCount(1, Speculum::$entriesQueue);
        $this->assertSame('retrieved', Speculum::$entriesQueue[0]->content['action']);
        $this->assertSame(3, Speculum::$entriesQueue[0]->content['count']);

        $entries = $this->loadSpeculumEntries();
        $this->assertSame(EntryType::Model->value, $entries[0]->type);
        $this->assertSame(3, $entries[0]->content['count']);
    }
}
