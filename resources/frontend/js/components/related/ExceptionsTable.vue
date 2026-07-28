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
                <th>Message</th>
                <th />
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="item in items"
                :key="item.id"
            >
                <td :title="item.content.class">
                    <entry-title
                        :to="{ name: 'exception-preview', params: { id: item.id } }"
                        :text="item.content.class"
                        :limit="70"
                        :subtitle="truncate(item.content.message, 200)"
                    />
                </td>
                <view-link-cell :to="{ name: 'exception-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
