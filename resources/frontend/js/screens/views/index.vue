<script setup>
import { useHelpers } from '@/composables/useHelpers';

const { truncate } = useHelpers();
</script>

<template>
    <index-screen
        title="Views"
        resource="views"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Name
                </th>
                <th
                    scope="col"
                    class="text-end"
                >
                    Composers
                </th>
                <th scope="col">
                    Happened
                </th>
                <th scope="col" />
            </tr>
        </template>

        <template #row="slotProps">
            <td>
                <entry-title
                    :to="{ name: 'view-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.name"
                    :limit="200"
                    :subtitle="truncate(slotProps.entry.content.path, 100)"
                />
            </td>

            <td class="table-fit text-end text-muted">
                {{ slotProps.entry.content.composers ? slotProps.entry.content.composers.length : 0 }}
            </td>

            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'view-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
