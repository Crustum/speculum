<script setup>
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
});

const summary = computed(() => {
    const time = props.items
        .reduce((sum, q) => sum + parseFloat(q.content.time || 0), 0)
        .toFixed(2);
    const groups = new Set(props.items.map((q) => `${q.content.hash}-${q.content.connection}`));

    return {
        time,
        duplicated: props.items.length - groups.size,
    };
});
</script>

<template>
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>
                    Query<br>
                    <small>{{ items.length }} queries, {{ summary.duplicated }} of which are duplicated.</small>
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
                <td :title="item.content.sql">
                    <entry-link
                        :to="{ name: 'query-preview', params: { id: item.id } }"
                        :text="item.content.sql"
                        :limit="110"
                        code
                    />
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
                <view-link-cell :to="{ name: 'query-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
