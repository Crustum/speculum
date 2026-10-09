<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { historyLine } from '@/utils/aiTool';
import { useInvocationHistory } from './invocation/useInvocationHistory';
import RunHeader from './invocation/RunHeader.vue';
import TimelineItem from './invocation/TimelineItem.vue';
import AgentItemBody from './invocation/AgentItemBody.vue';
import ToolItemBody from './invocation/ToolItemBody.vue';
import GenericItemBody from './invocation/GenericItemBody.vue';

const route = useRoute();
const invocationId = computed(() => String(route.params.id ?? ''));
const {
    ready,
    loadError,
    loadedCount,
    summary,
    countLine,
    timelineItems,
} = useInvocationHistory(invocationId);

const stepNumbers = computed(() => {
    const seen = new Set();
    for (const item of timelineItems.value) {
        const step = item?.main?.content?.step;
        if (item?.category === 'agent' && step != null && !seen.has(step)) seen.add(step);
    }
    return [...seen].sort((a, b) => a - b);
});

function jumpToStep(step) {
    const el = document.getElementById(`ai-step-${step}`);
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    el.classList.remove('step-flash');
    void el.offsetWidth;
    el.classList.add('step-flash');
    setTimeout(() => el.classList.remove('step-flash'), 1600);
}

function stepAnchor(item) {
    const step = item?.main?.content?.step;
    return item?.category === 'agent' && step != null ? `ai-step-${step}` : undefined;
}
</script>

<template>
    <div>
        <div class="d-flex align-items-center gap-2 mb-3">
            <router-link
                :to="{ name: 'ai' }"
                class="control-action"
            >
                ← AI
            </router-link>
            <h2 class="h6 m-0">
                Invocation history
            </h2>
        </div>

        <div
            v-if="!ready"
            class="d-flex align-items-center justify-content-center card-bg-secondary p-5 bottom-radius"
        >
            <p class="mb-0 text-muted">
                Loading…{{ loadedCount > 0 ? ` ${loadedCount} events` : '' }}
            </p>
        </div>

        <div
            v-else-if="loadError"
            class="card overflow-hidden"
        >
            <div class="card-body">
                <p class="mb-0">
                    Failed to load entries.
                </p>
            </div>
        </div>

        <div
            v-else-if="!summary.total"
            class="card overflow-hidden"
        >
            <div class="card-body">
                <p class="mb-0 text-muted">
                    No AI events recorded for this invocation.
                </p>
            </div>
        </div>

        <div v-else>
            <RunHeader
                :invocation-id="invocationId"
                :summary="summary"
                :count-line="countLine"
            />

            <div class="card overflow-hidden mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        Timeline <span class="badge badge-info">{{ timelineItems.length }}</span>
                    </h5>
                    <div
                        v-if="stepNumbers.length"
                        class="d-flex flex-wrap align-items-center gap-1 mt-2"
                    >
                        <span class="small text-muted me-1">Steps:</span>
                        <button
                            v-for="step in stepNumbers"
                            :key="step"
                            type="button"
                            class="btn btn-sm btn-muted"
                            @click="jumpToStep(step)"
                        >
                            {{ step }}
                        </button>
                    </div>
                </div>
                <div class="card-body card-bg-secondary d-flex flex-column gap-3">
                    <TimelineItem
                        v-for="item in timelineItems"
                        :key="item.key"
                        :item="item"
                        :anchor-id="stepAnchor(item)"
                    >
                        <AgentItemBody
                            v-if="item.category === 'agent' && item.hasBody"
                            :item="item"
                        />
                        <ToolItemBody
                            v-else-if="item.category === 'tool'"
                            :item="item"
                        />
                        <GenericItemBody
                            v-else-if="item.hasBody"
                            :item="item"
                        />
                        <div
                            v-else
                            class="text-muted"
                        >
                            {{ historyLine(item.entry) }}
                        </div>
                    </TimelineItem>
                </div>
            </div>
        </div>
    </div>
</template>
