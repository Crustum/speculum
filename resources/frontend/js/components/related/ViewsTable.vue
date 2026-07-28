<script setup>
import { useHelpers } from '../../composables/useHelpers';

defineProps({
    items: { type: Array, default: () => [] },
});

const { truncate } = useHelpers();
</script>

<template>
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Name</th>
                <th class="text-end">
                    Composers
                </th>
                <th />
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="item in items"
                :key="item.id"
            >
                <td>
                    <entry-title
                        :to="{ name: 'view-preview', params: { id: item.id } }"
                        :text="item.content.name"
                        :limit="200"
                        :subtitle="truncate(item.content.path, 100)"
                    />
                </td>
                <td class="table-fit text-end text-muted">
                    {{ item.content.composers ? item.content.composers.length : 0 }}
                </td>
                <view-link-cell :to="{ name: 'view-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
