<script setup>
import { useEntryStyles } from '../../composables/useEntryStyles';

defineProps({
    items: { type: Array, default: () => [] },
});

const { cacheActionTypeClass } = useEntryStyles();
</script>

<template>
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Key</th>
                <th>Action</th>
                <th />
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="item in items"
                :key="item.id"
            >
                <td :title="item.content.key">
                    <entry-link
                        :to="{ name: 'cache-preview', params: { id: item.id } }"
                        :text="item.content.key"
                        :limit="100"
                    />
                </td>
                <td class="table-fit">
                    <status-badge
                        :label="item.content.type"
                        :variant="cacheActionTypeClass(item.content.type)"
                    />
                </td>
                <view-link-cell :to="{ name: 'cache-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
