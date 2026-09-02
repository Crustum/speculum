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
                <th>Category</th>
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
                        :to="{ name: 'ai-preview', params: { id: item.id } }"
                        :text="item.content.name"
                        :limit="80"
                    >
                        <template #flags>
                            <flag-badge
                                :show="!!item.content.failed"
                                label="Failed"
                                variant="danger"
                            />
                        </template>
                    </entry-title>
                </td>
                <td>
                    <span class="badge badge-info text-uppercase">
                        {{ item.content.category }}
                    </span>
                </td>
                <view-link-cell :to="{ name: 'ai-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
