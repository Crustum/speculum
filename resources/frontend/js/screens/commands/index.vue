<script setup>
</script>

<template>
    <index-screen
        title="Commands"
        resource="commands"
        slow-filter-label="Show Slow Commands"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Command
                </th>
                <th
                    scope="col"
                    class="table-fit"
                >
                    Exit Code
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
                    :to="{ name: 'command-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.command"
                    :limit="90"
                    code
                />
            </td>

            <td class="table-fit text-center text-muted">
                {{ slotProps.entry.content.exit_code }}
            </td>

            <td class="table-fit text-end text-muted">
                <status-badge
                    v-if="slotProps.entry.content.slow"
                    :label="slotProps.entry.content.duration + 'ms'"
                    variant="danger"
                />
                <span v-else-if="slotProps.entry.content.duration != null">{{ slotProps.entry.content.duration }}ms</span>
                <span v-else>-</span>
            </td>

            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'command-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
