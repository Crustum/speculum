<script setup>
import { aiCategory } from '@/utils/aiCategory';
import { toolCallLine } from '@/utils/aiTool';

defineProps({
    items: { type: Array, default: () => [] },
});

function subtitle(item) {
    if (aiCategory(item) === 'tool') {
        return toolCallLine(item);
    }

    return item?.content?.summary || null;
}
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
                    <div
                        v-if="subtitle(item)"
                        class="small text-muted text-truncate"
                        style="max-width: 320px;"
                        :title="subtitle(item)"
                    >
                        {{ subtitle(item) }}
                    </div>
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
