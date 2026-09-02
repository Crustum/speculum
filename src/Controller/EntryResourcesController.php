<?php
declare(strict_types=1);

namespace Crustum\Speculum\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Crustum\Speculum\Entry\EntryResult;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Registry\EntryResource;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Override;

/**
 * Shared Speculum JSON API for registered entry resources (list + show).
 *
 * Mail, exceptions, and BlazeCast keep dedicated controllers for custom actions.
 */
class EntryResourcesController extends EntryController
{
    /**
     * Resolved entry resource for the current request path.
     *
     * @var \Crustum\Speculum\Registry\EntryResource|null
     */
    private ?EntryResource $resolved = null;

    /**
     * @inheritDoc
     */
    protected function entryType(): string
    {
        $type = $this->resource()->type;

        return is_array($type) ? (string)$type[0] : $type;
    }

    /**
     * @inheritDoc
     */
    protected function watcher(): string
    {
        return $this->resource()->watcher;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function index(): ?Response
    {
        $entries = $this->entries->get(
            $this->resource()->type,
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
     * @inheritDoc
     */
    #[Override]
    protected function status(): string
    {
        $soft = $this->resource()->soft;
        if ($soft !== null && !$this->isSoftAvailableAny($soft)) {
            return 'off';
        }

        return parent::status();
    }

    /**
     * A resource is available when at least one of its soft feature gates is available.
     *
     * @param \Crustum\Speculum\Enum\SoftFeature|array<\Crustum\Speculum\Enum\SoftFeature> $soft
     */
    private function isSoftAvailableAny(SoftFeature|array $soft): bool
    {
        if ($soft instanceof SoftFeature) {
            return WatcherRegistry::isSoftAvailable($soft);
        }

        return array_any($soft, fn(SoftFeature $feature): bool => WatcherRegistry::isSoftAvailable($feature));
    }

    /**
     * Resolve the registered resource for this request.
     *
     * @return \Crustum\Speculum\Registry\EntryResource
     */
    private function resource(): EntryResource
    {
        if ($this->resolved instanceof EntryResource) {
            return $this->resolved;
        }

        $path = (string)$this->request->getParam('resource');
        $resource = WatcherRegistry::entryResource($path);
        if (!$resource instanceof EntryResource) {
            throw new NotFoundException();
        }

        $this->resolved = $resource;

        return $resource;
    }
}
