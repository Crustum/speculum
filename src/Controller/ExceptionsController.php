<?php
declare(strict_types=1);

namespace Crustum\Speculum\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\I18n\DateTime;
use Crustum\Speculum\Entry\EntryResult;
use Crustum\Speculum\Entry\EntryUpdate;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Storage\EntryQueryOptions;
use Crustum\Speculum\Watcher\ExceptionWatcher;
use DateTimeInterface;
use Throwable;

/**
 * Speculum API controller for exception entries.
 */
class ExceptionsController extends EntryController
{
    /**
     * @inheritDoc
     */
    protected function entryType(): string
    {
        return EntryType::Exception->value;
    }

    /**
     * @inheritDoc
     */
    protected function watcher(): string
    {
        return ExceptionWatcher::class;
    }

    /**
     * Mark an exception as resolved.
     *
     * @param string $id Entry UUID.
     * @return \Cake\Http\Response|null
     */
    public function edit(string $id): ?Response
    {
        try {
            $entry = $this->entries->find($id);
        } catch (Throwable) {
            throw new NotFoundException();
        }

        if ($this->request->getData('resolved_at') === 'now') {
            $update = new EntryUpdate($entry->id, $entry->type, [
                'resolved_at' => DateTime::now()->format(DateTimeInterface::ATOM),
            ]);
            $this->entries->update([$update]);
            $entry = $this->entries->find($id);
        }

        $batch = $this->entries->get(null, EntryQueryOptions::forBatchId($entry->batchId)->limit(-1));
        $this->set([
            'entry' => $entry->jsonSerialize(),
            'batch' => array_map(static fn(EntryResult $item): array => $item->jsonSerialize(), $batch),
        ]);
        $this->viewBuilder()->setOption('serialize', ['entry', 'batch']);

        return null;
    }
}
