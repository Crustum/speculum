<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Cake\Event\EventManager;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Sanitizer\SensitiveData;
use Crustum\Speculum\Speculum;
use Throwable;

/**
 * Records ORM save/delete and find hydrations (CakePHP 5 has no Model.afterFind).
 */
class ModelWatcher extends Watcher implements EventListenerInterface
{
    /**
     * Pending hydration entries keyed by table alias.
     *
     * @var array<string, \Crustum\Speculum\Entry\IncomingEntry>
     */
    public array $hydrationEntries = [];

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        EventManager::instance()->on($this);

        Speculum::afterStoring(function (): void {
            $this->flush();
        });
    }

    /**
     * Return the Cake model events handled by this watcher.
     *
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        $events = $this->options['events'] ?? [
            'Model.afterSave',
            'Model.afterDelete',
        ];

        $map = [];
        foreach ($events as $eventName) {
            $eventName = (string)$eventName;
            if ($eventName === 'Model.afterFind') {
                continue;
            }

            $map[$eventName] = 'recordFromEvent';
        }

        if ($this->options['hydrations'] ?? true) {
            $map['Model.beforeFind'] = 'recordBeforeFind';
        }

        return $map;
    }

    /**
     * Record a model action or hydration from a Cake event.
     *
     * @param \Cake\Event\EventInterface<object> $event Model event.
     * @return void
     */
    public function recordFromEvent(EventInterface $event): void
    {
        $entity = $event->getData('entity');
        $this->recordAction($event->getName(), $event->getSubject(), $entity);
    }

    /**
     * Attach a formatResults counter — CakePHP 5 does not dispatch Model.afterFind.
     *
     * @param \Cake\Event\EventInterface<object> $event beforeFind event.
     * @param \Cake\ORM\Query\SelectQuery<\Cake\Datasource\EntityInterface>|mixed $query Query.
     * @param \ArrayObject|mixed $options Find options.
     * @param mixed|bool $primary Whether this is the primary find.
     * @return void
     */
    public function recordBeforeFind(
        EventInterface $event,
        mixed $query = null,
        mixed $options = null,
        mixed $primary = true,
    ): void {
        if (!Speculum::isRecording() || !($this->options['hydrations'] ?? true)) {
            return;
        }

        if ($primary === false || !$query instanceof SelectQuery) {
            return;
        }

        $subject = $event->getSubject();
        if ($this->shouldIgnoreSubject($subject)) {
            return;
        }

        $query->formatResults(function ($results) use ($subject) {
            $count = is_countable($results) ? count($results) : iterator_count($results);
            if ($count > 0) {
                $this->recordHydrationCount($subject, $count);
            }

            return $results;
        });
    }

    /**
     * Record a model create/update/delete action entry.
     *
     * @param string $event Event name.
     * @param mixed $subject Event subject.
     * @param mixed $entity Entity.
     * @return void
     */
    public function recordAction(string $event, mixed $subject, mixed $entity): void
    {
        if (!Speculum::isRecording()) {
            return;
        }

        if ($this->shouldIgnoreSubject($subject, $entity)) {
            return;
        }

        if (str_contains(strtolower($event), 'find') || str_contains(strtolower($event), 'retrieved')) {
            $this->recordHydrations($subject, $entity);

            return;
        }

        $modelClass = $this->formatModel($subject, $entity);
        $changes = null;
        if ($entity instanceof EntityInterface) {
            $dirty = $entity->getDirty();
            if ($dirty !== []) {
                $changes = $this->hideModelChanges($entity->extract($dirty));
            }
        }

        Speculum::recordEntry(EntryType::Model, IncomingEntry::make(array_filter([
            'action' => $this->action($event, $entity),
            'model' => $modelClass,
            'changes' => $changes,
        ], static fn($value): bool => $value !== null))->tags([$modelClass]));
    }

    /**
     * Redact Speculum-configured sensitive attributes from model changes.
     *
     * Does not use entity `$_hidden` / `getHidden()` — that list is for JSON
     * serialization, not security.
     *
     * @param array<string, mixed> $changes Dirty attribute values.
     * @return array<string, mixed>
     */
    protected function hideModelChanges(array $changes): array
    {
        return SensitiveData::modelAttributes($changes);
    }

    /**
     * Record model hydration for a retrieved entity.
     *
     * @param mixed $subject Table/subject.
     * @param mixed $entity Entity.
     * @return void
     */
    public function recordHydrations(mixed $subject, mixed $entity): void
    {
        if (!($this->options['hydrations'] ?? false)) {
            return;
        }

        $this->recordHydrationCount($subject, 1);
    }

    /**
     * Record a model hydration count for a table subject.
     *
     * @param mixed $subject Table subject.
     * @param int $count Hydrated row count.
     * @return void
     */
    public function recordHydrationCount(mixed $subject, int $count): void
    {
        if (!Speculum::isRecording() || $count < 1) {
            return;
        }

        if ($this->shouldIgnoreSubject($subject)) {
            return;
        }

        $modelClass = $this->formatModel($subject, null);
        if (!isset($this->hydrationEntries[$modelClass])) {
            $this->hydrationEntries[$modelClass] = IncomingEntry::make([
                'action' => 'retrieved',
                'model' => $modelClass,
                'count' => $count,
            ])->tags([$modelClass]);
            Speculum::recordEntry(EntryType::Model, $this->hydrationEntries[$modelClass]);

            return;
        }

        $this->hydrationEntries[$modelClass]->content['count'] += $count;
    }

    /**
     * Flush pending hydration entries.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->hydrationEntries = [];
    }

    /**
     * Resolve the model action name for an event.
     *
     * @param string $event Event name.
     * @param mixed $entity Entity when available.
     * @return string
     */
    protected function action(string $event, mixed $entity = null): string
    {
        $lower = strtolower($event);
        if (str_contains($lower, 'delete')) {
            return 'deleted';
        }

        if (str_contains($lower, 'save')) {
            if ($entity instanceof EntityInterface && $entity->isNew()) {
                return 'created';
            }

            return 'updated';
        }

        return 'retrieved';
    }

    /**
     * Determine whether the model subject should be ignored.
     *
     * @param mixed $subject Subject.
     * @param mixed $entity Entity when available.
     * @return bool
     */
    protected function shouldIgnoreSubject(mixed $subject, mixed $entity = null): bool
    {
        $namespaces = array_values(array_map(strval(...), $this->options['ignore_namespaces'] ?? []));
        if ($entity instanceof EntityInterface) {
            $entityClass = $entity::class;
            if (
                array_any(
                    $namespaces,
                    static fn(string $prefix): bool => $prefix !== '' && str_starts_with($entityClass, $prefix),
                )
            ) {
                return true;
            }
        }

        if (!$subject instanceof Table) {
            return false;
        }

        $table = $subject->getTable();
        if (str_starts_with($table, 'speculum_')) {
            return true;
        }

        $connections = array_values(array_map(strval(...), $this->options['ignore_connections'] ?? []));
        try {
            if (in_array($subject->getConnection()->configName(), $connections, true)) {
                return true;
            }
        } catch (Throwable) {
        }

        $entityClass = $subject->getEntityClass();

        return array_any(
            $namespaces,
            static fn(string $prefix): bool => $prefix !== '' && str_starts_with($entityClass, $prefix),
        );
    }

    /**
     * Format a model label from a table subject and entity.
     *
     * @param mixed $subject Subject.
     * @param mixed $entity Entity.
     * @return string
     */
    protected function formatModel(mixed $subject, mixed $entity): string
    {
        if ($entity instanceof EntityInterface) {
            return $entity::class;
        }

        if ($subject instanceof Table) {
            return $subject->getEntityClass();
        }

        if (is_object($subject)) {
            return $subject::class;
        }

        return (string)$subject;
    }
}
