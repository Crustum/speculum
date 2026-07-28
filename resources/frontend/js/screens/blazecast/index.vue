<script setup>
function directionLabel(entry) {
    if (entry.content?.direction === 'in' || entry.type === 'bc_message') {
        return 'Incoming';
    }

    return 'Outgoing';
}

function directionVariant(entry) {
    return directionLabel(entry) === 'Incoming' ? 'info' : 'secondary';
}

function channel(entry) {
    return entry.content?.channel || '-';
}

function detail(entry) {
    if (entry.type === 'bc_message') {
        return entry.content?.connection_id || '-';
    }

    const delivered = entry.content?.delivered_to;

    return delivered === undefined || delivered === null ? '-' : String(delivered);
}
</script>

<template>
    <index-screen
        title="BlazeCast"
        resource="blazecast"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Event
                </th>
                <th scope="col">
                    Channel
                </th>
                <th scope="col">
                    Direction
                </th>
                <th scope="col">
                    Detail
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
                    :to="{ name: 'blazecast-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.event || '-'"
                    :limit="70"
                />
            </td>
            <muted-text-cell
                :text="channel(slotProps.entry)"
                :limit="40"
            />
            <td class="table-fit">
                <status-badge
                    :label="directionLabel(slotProps.entry)"
                    :variant="directionVariant(slotProps.entry)"
                />
            </td>
            <muted-text-cell
                :text="detail(slotProps.entry)"
                :limit="28"
            />
            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'blazecast-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
