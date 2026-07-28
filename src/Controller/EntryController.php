<?php
declare(strict_types=1);

namespace Crustum\Speculum\Controller;

use Cake\Cache\Cache;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Crustum\Speculum\Contract\EntriesRepository;
use Crustum\Speculum\Entry\EntryResult;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Throwable;

/**
 * Base JSON controller for Speculum API resources.
 */
abstract class EntryController extends Controller
{
    /**
     * Entries repository used by this controller.
     *
     * @var \Crustum\Speculum\Contract\EntriesRepository
     */
    protected EntriesRepository $entries;

    /**
     * Return the entry type constant for this resource.
     *
     * @return string Entry type constant.
     */
    abstract protected function entryType(): string;

    /**
     * Return the watcher class used for recording status checks.
     *
     * @return class-string Watcher class for status checks.
     */
    abstract protected function watcher(): string;

    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->entries = Speculum::getRepository();
        $this->viewBuilder()->setClassName('Json');
        $this->request = $this->request->withParsedBody(
            $this->request->getParsedBody() ?: [],
        );
    }

    /**
     * @inheritDoc
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        if ($this->components()->has('FormProtection')) {
            $this->FormProtection->setConfig('validate', false);
        }
    }

    /**
     * List entries of this type.
     *
     * @return \Cake\Http\Response|null
     */
    public function index(): ?Response
    {
        $entries = $this->entries->get(
            $this->entryType(),
            EntryQueryOptions::fromRequest($this->request),
        );

        $this->set([
            'entries' => array_map(static fn(EntryResult $entry): array => $entry->jsonSerialize(), $entries),
            'status' => $this->status(),
        ]);
        $this->viewBuilder()->setOption('serialize', ['entries', 'status']);

        return null;
    }

    /**
     * Show a single entry and its batch.
     *
     * @param string $id Entry UUID.
     * @return \Cake\Http\Response|null
     */
    public function view(string $id): ?Response
    {
        try {
            $entry = $this->entries->find($id)->generateAvatar();
        } catch (Throwable) {
            throw new NotFoundException();
        }

        $batch = $this->entries->get(null, EntryQueryOptions::forBatchId($entry->batchId)->limit(-1));

        $this->set([
            'entry' => $entry->jsonSerialize(),
            'batch' => array_map(static fn(EntryResult $item): array => $item->jsonSerialize(), $batch),
        ]);
        $this->viewBuilder()->setOption('serialize', ['entry', 'batch']);

        return null;
    }

    /**
     * Resolve recording status for this entry type.
     *
     * @return string
     */
    protected function status(): string
    {
        if (!Configure::read('Speculum.enabled', false)) {
            return 'disabled';
        }

        try {
            if (Cache::read(Speculum::PAUSE_CACHE_KEY)) {
                return 'paused';
            }
        } catch (Throwable) {
        }

        $watcher = Configure::read('Speculum.watchers.' . $this->watcher());
        if ($watcher === false || $watcher === null) {
            return 'off';
        }

        if (is_array($watcher) && !($watcher['enabled'] ?? true)) {
            return 'off';
        }

        return 'enabled';
    }
}
