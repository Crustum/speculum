<?php
declare(strict_types=1);

namespace Crustum\Speculum\Entry;

use Crustum\Speculum\Enum\EntryType;

/**
 * Incoming VarDump entry with batch entry-point metadata.
 */
class IncomingVarDumpEntry extends IncomingEntry
{
    /**
     * Attach the request, job, or command that produced this VarDump.
     *
     * @param list<\Crustum\Speculum\Entry\IncomingEntry> $entries Batch entries.
     * @return void
     */
    public function assignEntryPoint(array $entries): void
    {
        $entryPoint = array_find($entries, fn($entry): bool => in_array($entry->type, [EntryType::Request->value, EntryType::Job->value, EntryType::Command->value], true));
        if ($entryPoint === null) {
            return;
        }

        $this->content = array_merge($this->content, [
            'entry_point_type' => $entryPoint->type,
            'entry_point_uuid' => $entryPoint->uuid,
            'entry_point_description' => $this->entryPointDescription($entryPoint),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function isVarDump(): bool
    {
        return true;
    }

    /**
     * Human-readable description of the entry point.
     *
     * @param \Crustum\Speculum\Entry\IncomingEntry $entryPoint Entry point.
     * @return string
     */
    protected function entryPointDescription(IncomingEntry $entryPoint): string
    {
        return match ($entryPoint->type) {
            EntryType::Request->value => trim(
                ($entryPoint->content['method'] ?? '') . ' ' . ($entryPoint->content['uri'] ?? ''),
            ),
            EntryType::Job->value => (string)($entryPoint->content['name'] ?? ''),
            EntryType::Command->value => (string)($entryPoint->content['command'] ?? ''),
            default => '',
        };
    }
}
