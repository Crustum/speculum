<script setup>
import { useHelpers } from '@/composables/useHelpers';

const { truncate } = useHelpers();
</script>

<template>
    <index-screen
        title="Notifications"
        resource="notifications"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Notification
                </th>
                <th scope="col">
                    Channel
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
                    :to="{ name: 'notification-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.notification || '-'"
                    :limit="70"
                >
                    <template #flags>
                        <flag-badge
                            :show="!!slotProps.entry.content.queued"
                            label="Queued"
                        />
                    </template>
                    <template #subtitle>
                        <span :title="slotProps.entry.content.notifiable">
                            Recipient: {{ truncate(slotProps.entry.content.notifiable, 90) }}
                        </span>
                    </template>
                </entry-title>
            </td>

            <muted-text-cell
                fit
                :text="slotProps.entry.content.channel"
                :limit="20"
            />
            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'notification-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
