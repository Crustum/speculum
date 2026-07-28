<script setup>
defineProps({
    items: { type: Array, default: () => [] },
});
</script>

<template>
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Batch</th>
                <th>Status</th>
                <th class="text-end">
                    Size
                </th>
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
                        :to="{ name: 'batch-preview', params: { id: item.id } }"
                        :text="item.content.name || item.content.id"
                        :limit="68"
                        :subtitle="'Connection: ' + item.content.connection + ' | Queue: ' + item.content.queue"
                    />
                </td>
                <td>
                    <status-badge
                        v-if="item.content.failedJobs > 0 && item.content.progress < 100"
                        label="Failures"
                        variant="danger"
                        size="sm"
                    />
                    <status-badge
                        v-if="item.content.progress == 100"
                        label="Finished"
                        variant="success"
                        size="sm"
                    />
                    <status-badge
                        v-if="
                            item.content.totalJobs == 0 ||
                                (item.content.pendingJobs > 0 && !item.content.failedJobs)
                        "
                        label="Pending"
                        variant="secondary"
                        size="sm"
                    />
                </td>
                <td class="text-end text-muted">
                    {{ item.content.totalJobs }}
                </td>
                <view-link-cell :to="{ name: 'batch-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
