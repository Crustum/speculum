<template>
    <index-screen
        title="Batches"
        resource="batches"
        hide-search="true"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Batch
                </th>
                <th scope="col">
                    Status
                </th>
                <th
                    scope="col"
                    class="text-end"
                >
                    Size
                </th>
                <th
                    scope="col"
                    class="text-end"
                >
                    Completion
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
                    :to="{ name: 'batch-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.name || slotProps.entry.content.id"
                    :limit="68"
                    :subtitle="'Connection: ' + slotProps.entry.content.connection + ' | Queue: ' + slotProps.entry.content.queue"
                />
            </td>

            <td>
                <status-badge
                    v-if="slotProps.entry.content.failedJobs > 0 && slotProps.entry.content.progress < 100"
                    label="Failures"
                    variant="danger"
                    size="sm"
                />
                <status-badge
                    v-if="slotProps.entry.content.progress == 100"
                    label="Finished"
                    variant="success"
                    size="sm"
                />
                <status-badge
                    v-if="
                        slotProps.entry.content.totalJobs == 0 ||
                            (slotProps.entry.content.pendingJobs > 0 && !slotProps.entry.content.failedJobs)
                    "
                    label="Pending"
                    variant="secondary"
                    size="sm"
                />
            </td>
            <td class="text-end text-muted">
                {{ slotProps.entry.content.totalJobs }}
            </td>
            <td class="text-end text-muted">
                {{ slotProps.entry.content.progress }}%
            </td>

            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'batch-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
