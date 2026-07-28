<template>
    <index-screen
        title="Broadcasts"
        resource="broadcasts"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Event
                </th>
                <th scope="col">
                    Channels
                </th>
                <th scope="col">
                    Connection
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
                    :to="{ name: 'broadcast-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.event || '-'"
                    :limit="70"
                >
                    <template #flags>
                        <flag-badge
                            :show="!!slotProps.entry.content.queued"
                            label="Queued"
                        />
                    </template>
                </entry-title>
            </td>

            <muted-text-cell
                :text="(slotProps.entry.content.channels || []).join(', ')"
                :limit="60"
            />
            <muted-text-cell
                fit
                :text="slotProps.entry.content.connection || '-'"
                :limit="20"
            />
            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'broadcast-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
