<script setup>
import { useEntryStyles } from '@/composables/useEntryStyles';

const { requestMethodClass, requestStatusClass } = useEntryStyles();
</script>

<template>
    <index-screen
        title="Requests"
        resource="requests"
        slow-filter-label="Show Slow Requests"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Verb
                </th>
                <th scope="col">
                    Path
                </th>
                <th
                    scope="col"
                    class="text-center"
                >
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
            <td class="table-fit pe-0">
                <status-badge
                    :label="slotProps.entry.content.method"
                    :variant="requestMethodClass(slotProps.entry.content.method)"
                />
            </td>

            <td>
                <entry-link
                    :to="{ name: 'request-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.uri"
                    :limit="50"
                />
            </td>

            <td class="table-fit text-center">
                <status-badge
                    :label="slotProps.entry.content.response_status"
                    :variant="requestStatusClass(slotProps.entry.content.response_status)"
                />
            </td>

            <td class="table-fit text-end text-muted">
                <status-badge
                    v-if="slotProps.entry.content.slow"
                    :label="slotProps.entry.content.duration + 'ms'"
                    variant="danger"
                />
                <span v-else-if="slotProps.entry.content.duration">{{ slotProps.entry.content.duration }}ms</span>
                <span v-else>-</span>
            </td>

            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'request-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
