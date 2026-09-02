<?php
declare(strict_types=1);

namespace Crustum\Speculum\Event;

use Cake\Event\EventInterface;
use Cake\Event\EventList;
use Cake\Event\EventManager;
use Crustum\Speculum\Watcher\EventWatcher;
use Override;
use ReflectionProperty;

/**
 * Global EventManager that records Cake dispatches matching fnmatch masks.
 *
 * CakePHP has no wildcard listeners. Local managers still notify this instance
 * via addEventToList when tracking is enabled.
 */
class RecordingEventManager extends EventManager
{
    /**
     * Active recording manager when installed as the global instance.
     *
     * @var self|null
     */
    protected static ?self $installed = null;

    /**
     * Event watcher that receives matching dispatches.
     *
     * @var \Crustum\Speculum\Watcher\EventWatcher
     */
    protected EventWatcher $watcher;

    /**
     * fnmatch patterns (for example `*`, `Model.*`).
     *
     * @var list<string>
     */
    protected array $masks = [];

    /**
     * Create a recording event manager.
     *
     * @param \Crustum\Speculum\Watcher\EventWatcher $watcher Event watcher.
     * @param list<string> $masks fnmatch masks.
     */
    public function __construct(EventWatcher $watcher, array $masks)
    {
        $this->watcher = $watcher;
        $this->masks = $masks;
    }

    /**
     * Install as the global EventManager, migrating existing listeners.
     *
     * @param \Crustum\Speculum\Watcher\EventWatcher $watcher Event watcher.
     * @param list<string> $masks fnmatch masks.
     * @return self
     */
    public static function install(EventWatcher $watcher, array $masks): self
    {
        $current = EventManager::instance();
        if ($current instanceof self) {
            $current->watcher = $watcher;
            $current->masks = array_values(array_unique(array_merge($current->masks, $masks)));

            return $current;
        }

        $manager = new self($watcher, $masks);
        self::migrateListeners($current, $manager);
        $manager->setEventList(new EventList());
        EventManager::instance($manager);
        self::$installed = $manager;

        return $manager;
    }

    /**
     * Restore a plain EventManager after tests.
     *
     * @return void
     */
    public static function uninstall(): void
    {
        if (!self::$installed instanceof RecordingEventManager && !(EventManager::instance() instanceof self)) {
            return;
        }

        $current = EventManager::instance();
        $replacement = new EventManager();
        self::migrateListeners($current, $replacement);

        EventManager::instance($replacement);
        self::$installed = null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function addEventToList(EventInterface $event)
    {
        $this->recordIfMatching($event);

        return $this;
    }

    /**
     * Record the event when its name matches a configured mask.
     *
     * @param \Cake\Event\EventInterface<object> $event Dispatched event.
     * @return void
     */
    protected function recordIfMatching(EventInterface $event): void
    {
        $name = $event->getName();
        if (!$this->matchesMask($name)) {
            return;
        }

        $this->watcher->recordEvent($name, $this->watcher->payloadFromEvent($event));
    }

    /**
     * Whether the event name matches any installed mask.
     *
     * @param string $eventName Event name.
     * @return bool
     */
    protected function matchesMask(string $eventName): bool
    {
        return array_any($this->masks, fn(string $mask): bool => fnmatch($mask, $eventName));
    }

    /**
     * Copy listener map from one manager onto another.
     *
     * @param \Cake\Event\EventManager $from Source manager.
     * @param \Cake\Event\EventManager $to Destination manager.
     * @return void
     */
    protected static function migrateListeners(EventManager $from, EventManager $to): void
    {
        $property = new ReflectionProperty(EventManager::class, '_listeners');
        $toListeners = $property->getValue($to);
        $fromListeners = $property->getValue($from);
        if (!is_array($fromListeners) || $fromListeners === []) {
            return;
        }

        if (!is_array($toListeners)) {
            $toListeners = [];
        }

        foreach ($fromListeners as $eventKey => $priorities) {
            if (!is_array($priorities)) {
                continue;
            }

            foreach ($priorities as $priority => $callables) {
                if (!is_array($callables)) {
                    continue;
                }

                foreach ($callables as $callable) {
                    $toListeners[$eventKey][$priority][] = $callable;
                }
            }
        }

        $property->setValue($to, $toListeners);
    }
}
