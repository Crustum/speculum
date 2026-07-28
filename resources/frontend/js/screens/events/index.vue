<template>
    <index-screen
        title="Events"
        resource="events"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Name
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
                    :to="{ name: 'event-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.name"
                    :limit="80"
                >
                    <template #flags>
                        <flag-badge
                            :show="!!slotProps.entry.content.broadcast"
                            label="Broadcast"
                        />
                    </template>
                </entry-title>
            </td>

            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'event-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
