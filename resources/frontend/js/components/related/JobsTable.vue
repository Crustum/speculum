<script setup>
import { useEntryStyles } from '../../composables/useEntryStyles';

defineProps({
    items: { type: Array, default: () => [] },
});

const { jobStatusClass } = useEntryStyles();
</script>

<template>
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Job</th>
                <th scope="col">
                    Status
                </th>
                <th
                    scope="col"
                    class="text-end"
                >
                    Duration
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
                        :to="{ name: 'job-preview', params: { id: item.id } }"
                        :text="item.content.name"
                        :limit="68"
                        :subtitle="'Connection: ' + item.content.connection + ' | Queue: ' + item.content.queue"
                    />
                </td>
                <td class="table-fit">
                    <status-badge
                        :label="item.content.status"
                        :variant="jobStatusClass(item.content.status)"
                    />
                </td>
                <td class="table-fit text-end text-muted">
                    <status-badge
                        v-if="item.content.slow"
                        :label="item.content.duration + 'ms'"
                        variant="danger"
                    />
                    <span v-else-if="item.content.duration != null">{{ item.content.duration }}ms</span>
                    <span v-else>-</span>
                </td>
                <view-link-cell :to="{ name: 'job-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
