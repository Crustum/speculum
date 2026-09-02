<script setup>
function isWrite(operation) {
    return operation === 'update' || operation === 'delete';
}

function primaryLabel(entry) {
    if (isWrite(entry.content.operation)) {
        return entry.content.query || `${entry.content.operation} · ${entry.content.index || entry.content.table || ''}`;
    }

    return entry.content.query || '(empty)';
}

function countLabel(entry) {
    if (isWrite(entry.content.operation)) {
        return entry.content.count ?? '—';
    }

    return entry.content.hits ?? '—';
}
</script>

<template>
    <index-screen
        title="Searches"
        resource="searches"
        show-all-family="true"
        slow-filter-label="Show Slow Searches"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Query / Write
                </th>
                <th scope="col">
                    Operation
                </th>
                <th
                    scope="col"
                    class="text-end"
                >
                    Hits / Count
                </th>
                <th
                    scope="col"
                    class="text-end"
                >
                    Duration
                </th>
                <th scope="col">
                    Happened
                </th>
                <th scope="col" />
            </tr>
        </template>

        <template #row="slotProps">
            <td>
                <entry-link
                    :to="{ name: 'searches-preview', params: { id: slotProps.entry.id } }"
                    :text="primaryLabel(slotProps.entry)"
                    :limit="90"
                    code
                />
            </td>

            <td class="table-fit">
                <status-badge
                    :label="slotProps.entry.content.operation || 'search'"
                    :variant="isWrite(slotProps.entry.content.operation) ? 'info' : 'secondary'"
                />
            </td>

            <td class="table-fit text-end text-muted">
                {{ countLabel(slotProps.entry) }}
            </td>

            <td class="table-fit text-end text-muted">
                <status-badge
                    v-if="slotProps.entry.content.slow"
                    :label="slotProps.entry.content.time + 'ms'"
                    variant="danger"
                />
                <span v-else>{{ slotProps.entry.content.time }}ms</span>
            </td>

            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'searches-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
