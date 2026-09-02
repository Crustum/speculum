<script setup>
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
});

const summary = computed(() => {
    const time = props.items
        .reduce((sum, item) => sum + parseFloat(item.content.time || 0), 0)
        .toFixed(2);

    return { time };
});

function isWrite(operation) {
    return operation === 'update' || operation === 'delete';
}

function primaryLabel(item) {
    if (isWrite(item.content.operation)) {
        return item.content.query || `${item.content.operation} · ${item.content.index || item.content.table || ''}`;
    }

    return item.content.query || '(empty)';
}

function countLabel(item) {
    if (isWrite(item.content.operation)) {
        return item.content.count ?? '—';
    }

    return item.content.hits ?? '—';
}
</script>

<template>
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>
                    Query / Write<br>
                    <small>{{ items.length }} entries</small>
                </th>
                <th>Operation</th>
                <th class="text-end">
                    Hits / Count
                </th>
                <th class="text-end">
                    Duration<br><small>{{ summary.time }}ms</small>
                </th>
                <th />
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="item in items"
                :key="item.id"
            >
                <td :title="primaryLabel(item)">
                    <entry-link
                        :to="{ name: 'searches-preview', params: { id: item.id } }"
                        :text="primaryLabel(item)"
                        :limit="110"
                        code
                    />
                </td>
                <td class="table-fit">
                    <status-badge
                        :label="item.content.operation || 'search'"
                        :variant="isWrite(item.content.operation) ? 'info' : 'secondary'"
                    />
                </td>
                <td class="table-fit text-end text-muted">
                    {{ countLabel(item) }}
                </td>
                <td class="table-fit text-end">
                    <span
                        v-if="item.content.slow"
                        class="badge badge-danger"
                    >{{ item.content.time }}ms</span>
                    <span
                        v-else
                        class="text-muted"
                    >{{ item.content.time }}ms</span>
                </td>
                <view-link-cell :to="{ name: 'searches-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
