<script setup>
</script>

<template>
    <index-screen
        title="Schedule"
        resource="schedule"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Command
                </th>
                <th scope="col">
                    Expression
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
                    :to="{ name: 'schedule-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.description || slotProps.entry.content.command"
                    :limit="85"
                    code
                />
            </td>

            <muted-text-cell
                fit
                :text="slotProps.entry.content.expression"
                :limit="200"
            />
            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'schedule-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
