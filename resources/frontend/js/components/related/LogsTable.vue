<script setup>
import { useEntryStyles } from '../../composables/useEntryStyles';

defineProps({
    items: { type: Array, default: () => [] },
});

const { logLevelClass } = useEntryStyles();
</script>

<template>
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Message</th>
                <th scope="col">
                    Level
                </th>
                <th />
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="item in items"
                :key="item.id"
            >
                <td :title="item.content.message">
                    <entry-link
                        :to="{ name: 'log-preview', params: { id: item.id } }"
                        :text="item.content.message"
                        :limit="90"
                    />
                </td>
                <td class="table-fit">
                    <status-badge
                        :label="item.content.level"
                        :variant="logLevelClass(item.content.level)"
                    />
                </td>
                <view-link-cell :to="{ name: 'log-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
