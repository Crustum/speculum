<script setup>
import { useEntryStyles } from '@/composables/useEntryStyles';

const { requestMethodClass, requestStatusClass } = useEntryStyles();
</script>

<template>
    <index-screen
        title="HTTP Client"
        resource="http-clients"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Verb
                </th>
                <th scope="col">
                    URI
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
            <td class="table-fit pe-0">
                <status-badge
                    :label="slotProps.entry.content.method"
                    :variant="requestMethodClass(slotProps.entry.content.method)"
                />
            </td>

            <td>
                <entry-link
                    :to="{ name: 'http-client-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.uri"
                    :limit="60"
                />
            </td>

            <td class="table-fit">
                <status-badge
                    :label="slotProps.entry.content.response_status !== undefined ? slotProps.entry.content.response_status : 'N/A'"
                    :variant="
                        requestStatusClass(
                            slotProps.entry.content.response_status !== undefined
                                ? slotProps.entry.content.response_status
                                : null
                        )
                    "
                />
            </td>

            <td class="table-fit text-end text-muted">
                <span v-if="slotProps.entry.content.duration">{{ slotProps.entry.content.duration }}ms</span>
                <span v-else>-</span>
            </td>

            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'http-client-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
