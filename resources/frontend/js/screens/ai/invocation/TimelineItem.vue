<script setup>
import { computed } from 'vue';
import { entryAgentClass, entryToolClass, formatDateTime } from '@/utils/aiTool';

const props = defineProps({
    item: { type: Object, required: true },
    anchorId: { type: String, default: undefined },
});

function shortEventName(entry) {
    const name = entry?.content?.name ?? '';

    return name.startsWith('Ai.') ? name.slice(3) : name;
}

const agentClass = computed(() => entryAgentClass(props.item.entry));
const toolClass = computed(() => entryToolClass(props.item.entry));

const title = computed(() => {    if (props.item.collapsed && props.item.start && props.item.finish) {
        return `${shortEventName(props.item.start)} → ${shortEventName(props.item.finish)}`;
    }

    const step = props.item.main.content.step;

    return step != null
        ? `${shortEventName(props.item.main)} · step ${step}`
        : shortEventName(props.item.main);
});
</script>

<template>
    <div
        :id="anchorId"
        class="card border p-2 step-card"
    >
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge badge-info text-uppercase">{{ item.category }}</span>
            <span class="small text-muted">{{ title }}</span>
            <span
                v-if="item.category === 'agent' && item.main.content.model"
                class="small text-muted"
            >{{ item.main.content.model }}</span>
            <span
                v-if="item.category === 'agent' && agentClass"
                class="small text-monospace text-muted text-truncate"
                style="max-width: 320px;"
                :title="agentClass"
            >{{ agentClass }}</span>
            <span
                v-if="item.category === 'tool' && toolClass"
                class="small text-monospace text-muted text-truncate"
                style="max-width: 320px;"
                :title="toolClass"
            >{{ toolClass }}</span>
            <flag-badge
                :show="!!item.main.content.failed"
                label="Failed"
                variant="danger"
            />
            <span
                v-if="item.running"
                class="small text-muted"
            >running…</span>
            <span class="ms-auto" />
            <span
                class="small text-muted text-nowrap"
                :title="item.main.created"
            >{{ formatDateTime(item.main.created) }}</span>
            <view-link-cell :to="{ name: 'ai-preview', params: { id: item.main.id } }" />
        </div>
        <slot />
    </div>
</template>

<style scoped>
.step-card {
    scroll-margin-top: 12px;
}
.step-card.step-flash {
    box-shadow: 0 0 0 2px var(--info, #17a2b8);
    transition: box-shadow 0.3s ease;
}
</style>
