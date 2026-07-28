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
</script>

<template>
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>
                    Command<br>
                    <small>{{ items.length }} commands</small>
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
                <td :title="item.content.summary || item.content.command">
                    <entry-link
                        :to="{ name: 'mongo-preview', params: { id: item.id } }"
                        :text="item.content.summary || item.content.command"
                        :limit="110"
                        code
                    />
                </td>
                <td class="table-fit text-end">
                    <span
                        v-if="item.content.slow || item.content.failed"
                        class="badge"
                        :class="item.content.failed ? 'badge-danger' : 'badge-warning'"
                    >{{ item.content.time }}ms</span>
                    <span
                        v-else
                        class="text-muted"
                    >{{ item.content.time }}ms</span>
                </td>
                <view-link-cell :to="{ name: 'mongo-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
