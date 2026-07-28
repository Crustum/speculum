<script setup>
import { useEntryStyles } from '@/composables/useEntryStyles';

const { logLevelClass } = useEntryStyles();
</script>

<template>
    <index-screen
        title="Logs"
        resource="logs"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Message
                </th>
                <th scope="col">
                    Level
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
                    :to="{ name: 'log-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.message"
                    :limit="50"
                />
            </td>

            <td class="table-fit">
                <status-badge
                    :label="slotProps.entry.content.level"
                    :variant="logLevelClass(slotProps.entry.content.level)"
                />
            </td>

            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'log-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
