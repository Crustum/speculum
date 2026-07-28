<script setup>
import { useEntryStyles } from '../../composables/useEntryStyles';

defineProps({
    items: { type: Array, default: () => [] },
});

const { modelActionClass } = useEntryStyles();
</script>

<template>
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Model</th>
                <th>Hydrated</th>
                <th>Action</th>
                <th />
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="item in items"
                :key="item.id"
            >
                <td :title="item.content.model">
                    <entry-link
                        :to="{ name: 'model-preview', params: { id: item.id } }"
                        :text="item.content.model"
                        :limit="100"
                    />
                </td>
                <td>{{ item.content?.count }}</td>
                <td class="table-fit">
                    <status-badge
                        :label="item.content.action"
                        :variant="modelActionClass(item.content.action)"
                    />
                </td>
                <view-link-cell :to="{ name: 'model-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
