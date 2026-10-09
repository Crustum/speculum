<script setup>
import { aiCategory } from '@/utils/aiCategory';
import { toolCallLine } from '@/utils/aiTool';

function summaryText(entry) {
    if (aiCategory(entry) === 'tool') {
        return toolCallLine(entry);
    }

    return entry?.content?.summary || '—';
}
</script>

<template>
    <index-screen
        title="AI"
        resource="ai"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Category
                </th>
                <th scope="col">
                    Event
                </th>
                <th scope="col">
                    Provider / Model
                </th>
                <th scope="col">
                    Summary
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
                <span class="badge badge-info text-uppercase">
                    {{ aiCategory(slotProps.entry) }}
                </span>
            </td>

            <td>
                <entry-title
                    :to="{ name: 'ai-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.name"
                    :limit="80"
                >
                    <template #flags>
                        <flag-badge
                            :show="!!slotProps.entry.content.failed"
                            label="Failed"
                            variant="danger"
                        />
                    </template>
                </entry-title>
            </td>

            <td class="text-muted">
                <span v-if="slotProps.entry.content.provider">{{ slotProps.entry.content.provider }}</span>
                <span v-if="slotProps.entry.content.model"> · {{ slotProps.entry.content.model }}</span>
                <span v-if="!slotProps.entry.content.provider && !slotProps.entry.content.model">—</span>
            </td>

            <td
                class="text-muted text-truncate"
                style="max-width: 280px;"
                :title="summaryText(slotProps.entry)"
            >
                {{ summaryText(slotProps.entry) }}
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
            <view-link-cell :to="{ name: 'ai-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
