<script setup>
</script>

<template>
    <index-screen
        title="Mongo Query Logs"
        resource="mongo-query-logs"
        slow-filter-label="Show Slow Queries"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Query
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
                    :to="{ name: 'mongo-query-log-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.query"
                    :limit="90"
                    code
                />
            </td>

            <td class="table-fit text-end text-muted">
                <status-badge
                    v-if="slotProps.entry.content.slow"
                    :label="slotProps.entry.content.time + 'ms'"
                    variant="danger"
                />
                <span v-else>{{ slotProps.entry.content.time }}ms</span>
            </td>

            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'mongo-query-log-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
