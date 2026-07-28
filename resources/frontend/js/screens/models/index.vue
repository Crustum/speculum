<script setup>
import { useEntryStyles } from '@/composables/useEntryStyles';

const { modelActionClass } = useEntryStyles();
</script>

<template>
    <index-screen
        title="Models"
        resource="models"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Model
                </th>
                <th scope="col">
                    Action
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
                    :to="{ name: 'model-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.model"
                    :limit="70"
                />
            </td>

            <td class="table-fit">
                <status-badge
                    :label="slotProps.entry.content.action"
                    :variant="modelActionClass(slotProps.entry.content.action)"
                />
            </td>

            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'model-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
