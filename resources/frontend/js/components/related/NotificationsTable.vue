<script setup>
import { useHelpers } from '../../composables/useHelpers';

defineProps({
    items: { type: Array, default: () => [] },
});

const { truncate } = useHelpers();
</script>

<template>
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Notification</th>
                <th>Channel</th>
                <th />
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="item in items"
                :key="item.id"
            >
                <td>
                    <entry-title
                        :to="{ name: 'notification-preview', params: { id: item.id } }"
                        :text="item.content.notification || '-'"
                        :limit="70"
                    >
                        <template #flags>
                            <flag-badge
                                :show="!!item.content.queued"
                                label="Queued"
                            />
                        </template>
                        <template #subtitle>
                            <span :title="item.content.notifiable">
                                Recipient: {{ truncate(item.content.notifiable, 90) }}
                            </span>
                        </template>
                    </entry-title>
                </td>
                <muted-text-cell
                    fit
                    :text="item.content.channel"
                    :limit="20"
                />
                <view-link-cell :to="{ name: 'notification-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
