<script setup>
defineProps({
    items: { type: Array, default: () => [] },
});
</script>

<template>
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Event</th>
                <th>Channels</th>
                <th>Connection</th>
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
                        :to="{ name: 'broadcast-preview', params: { id: item.id } }"
                        :text="item.content.event || '-'"
                        :limit="70"
                    >
                        <template #flags>
                            <flag-badge
                                :show="!!item.content.queued"
                                label="Queued"
                            />
                        </template>
                    </entry-title>
                </td>
                <muted-text-cell
                    :text="(item.content.channels || []).join(', ')"
                    :limit="60"
                />
                <muted-text-cell
                    fit
                    :text="item.content.connection || '-'"
                    :limit="20"
                />
                <view-link-cell :to="{ name: 'broadcast-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
