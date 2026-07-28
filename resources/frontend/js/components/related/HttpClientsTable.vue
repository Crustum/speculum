<script setup>
import { useEntryStyles } from '../../composables/useEntryStyles';

defineProps({
    items: { type: Array, default: () => [] },
});

const { requestMethodClass, requestStatusClass } = useEntryStyles();
</script>

<template>
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Verb</th>
                <th>URI</th>
                <th>Status</th>
                <th class="text-end">
                    Duration
                </th>
                <th class="text-end">
                    Happened
                </th>
                <th />
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="item in items"
                :key="item.id"
            >
                <td class="table-fit pe-0">
                    <status-badge
                        :label="item.content.method"
                        :variant="requestMethodClass(item.content.method)"
                    />
                </td>
                <td :title="item.content.uri">
                    <entry-link
                        :to="{ name: 'http-client-preview', params: { id: item.id } }"
                        :text="item.content.uri"
                        :limit="60"
                    />
                </td>
                <td class="table-fit">
                    <status-badge
                        :label="
                            item.content.response_status !== undefined ? item.content.response_status : 'N/A'
                        "
                        :variant="
                            requestStatusClass(
                                item.content.response_status !== undefined
                                    ? item.content.response_status
                                    : null
                            )
                        "
                    />
                </td>
                <td class="table-fit text-end text-muted">
                    <span v-if="item.content.duration">{{ item.content.duration }}ms</span>
                    <span v-else>-</span>
                </td>
                <time-ago-cell :value="item.created" />
                <view-link-cell :to="{ name: 'http-client-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
