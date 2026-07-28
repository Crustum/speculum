<?php
declare(strict_types=1);

namespace Crustum\Speculum\Controller;

use Cake\Http\Response;
use Crustum\Speculum\Entry\EntryResult;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Crustum\Speculum\Watcher\BlazeCastWatcher;
use Override;

/**
 * Speculum API for BlazeCast outbound deliveries and inbound messages.
 */
class BlazeCastController extends EntryController
{
    /**
     * @inheritDoc
     */
    protected function entryType(): string
    {
        return EntryType::BlazeCastDelivery->value;
    }

    /**
     * Entry types included in the unified BlazeCast list.
     *
     * @return list<string>
     */
    protected function entryTypes(): array
    {
        return [
            EntryType::BlazeCastDelivery->value,
            EntryType::BlazeCastMessage->value,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function watcher(): string
    {
        return BlazeCastWatcher::class;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function index(): ?Response
    {
        $entries = $this->entries->get(
            $this->entryTypes(),
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
        if (!WatcherRegistry::isSoftAvailable(SoftFeature::BlazeCast)) {
            return 'off';
        }

        return parent::status();
    }
}
