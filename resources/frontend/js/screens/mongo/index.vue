<script setup>
</script>

<template>
    <index-screen
        title="Mongo"
        resource="mongo"
        slow-filter-label="Show Slow Commands"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Command
                </th>
                <th
                    scope="col"
                    class="text-end"
                >
                    Duration
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
                    :to="{ name: 'mongo-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.summary || slotProps.entry.content.command"
                    :limit="90"
                    code
                />
            </td>

            <td class="table-fit text-end text-muted">
                <status-badge
                    v-if="slotProps.entry.content.slow || slotProps.entry.content.failed"
                    :label="slotProps.entry.content.time + 'ms'"
                    :variant="slotProps.entry.content.failed ? 'danger' : 'warning'"
                />
                <span v-else>{{ slotProps.entry.content.time }}ms</span>
            </td>

            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'mongo-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
