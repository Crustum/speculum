<script setup>
import { useEntryStyles } from '@/composables/useEntryStyles';

const { jobStatusClass } = useEntryStyles();
</script>

<template>
    <index-screen
        title="Jobs"
        resource="jobs"
        show-all-family="true"
        slow-filter-label="Show Slow Jobs"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Job
                </th>
                <th scope="col">
                    Status
                </th>
                <th
                    scope="col"
                    class="text-end"
                >
                    Duration
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
                    :to="{ name: 'job-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.name"
                    :limit="68"
                    :subtitle="'Connection: ' + slotProps.entry.content.connection + ' | Queue: ' + slotProps.entry.content.queue"
                />
            </td>

            <td class="table-fit">
                <status-badge
                    :label="slotProps.entry.content.status"
                    :variant="jobStatusClass(slotProps.entry.content.status)"
                />
            </td>

            <td class="table-fit text-end text-muted">
                <status-badge
                    v-if="slotProps.entry.content.slow"
                    :label="slotProps.entry.content.duration + 'ms'"
                    variant="danger"
                />
                <span v-else-if="slotProps.entry.content.duration != null">{{ slotProps.entry.content.duration }}ms</span>
                <span v-else>-</span>
            </td>

            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'job-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
