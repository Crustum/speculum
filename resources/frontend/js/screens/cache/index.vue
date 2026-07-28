<script setup>
import { useEntryStyles } from '@/composables/useEntryStyles';

const { cacheActionTypeClass } = useEntryStyles();
</script>

<template>
    <index-screen
        title="Cache"
        resource="cache"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Key
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
                    :to="{ name: 'cache-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.key"
                    :limit="80"
                />
            </td>

            <td class="table-fit">
                <status-badge
                    :label="slotProps.entry.content.type"
                    :variant="cacheActionTypeClass(slotProps.entry.content.type)"
                />
            </td>

            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'cache-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
