<script setup>
defineProps({
    items: { type: Array, default: () => [] },
});
</script>

<template>
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Name</th>
                <th />
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="item in items"
                :key="item.id"
            >
                <td :title="item.content.name">
                    <entry-title
                        :to="{ name: 'event-preview', params: { id: item.id } }"
                        :text="item.content.name"
                        :limit="80"
                    >
                        <template #flags>
                            <flag-badge
                                :show="!!item.content.broadcast"
                                label="Broadcast"
                                variant="info"
                            />
                        </template>
                    </entry-title>
                </td>
                <view-link-cell :to="{ name: 'event-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
