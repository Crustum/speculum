<script setup>
defineProps({
    items: { type: Array, default: () => [] },
});

function directionLabel(entry) {
    if (entry.content?.direction === 'in' || entry.type === 'bc_message') {
        return 'Incoming';
    }

    return 'Outgoing';
}

function directionVariant(entry) {
    return directionLabel(entry) === 'Incoming' ? 'info' : 'secondary';
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
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Event</th>
                <th>Channel</th>
                <th>Direction</th>
                <th>Detail</th>
                <th />
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="item in items"
                :key="item.id"
            >
                <td>
                    <entry-link
                        :to="{ name: 'blazecast-preview', params: { id: item.id } }"
                        :text="item.content.event || '-'"
                        :limit="70"
                    />
                </td>
                <muted-text-cell
                    :text="item.content.channel || '-'"
                    :limit="40"
                />
                <td class="table-fit">
                    <status-badge
                        :label="directionLabel(item)"
                        :variant="directionVariant(item)"
                    />
                </td>
                <muted-text-cell
                    :text="detail(item)"
                    :limit="28"
                />
                <view-link-cell :to="{ name: 'blazecast-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
