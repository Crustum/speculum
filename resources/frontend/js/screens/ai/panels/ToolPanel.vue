<script setup>
import { computed } from 'vue';
import InfoCard from './InfoCard.vue';
import JsonCard from './JsonCard.vue';

const props = defineProps({
    entry: { type: Object, required: true },
});

const content = computed(() => props.entry?.content ?? {});
const payload = computed(() => content.value.payload ?? {});

const agent = computed(() => payload.value.agent ?? {});
const tool = computed(() => payload.value.tool ?? {});
const toolProps = computed(() => tool.value.properties ?? {});

// The actual tool may be wrapped (e.g. aicoder's EventedTool decorates the real
// tool in `properties.inner`); resolve defensively either way.
const wrapped = computed(() => toolProps.value.inner ?? null);
const realToolClass = computed(() => wrapped.value?.class ?? tool.value.class ?? null);

// Invocation ID, Duration and Summary are already in the top-level table, so the
// Tool Call card only shows what is specific to the tool invocation itself.
const rows = computed(() => {
    const p = payload.value;
    const out = [];

    if (realToolClass.value) {
        out.push({ label: 'Tool', value: realToolClass.value, mono: true });
    }
    if (tool.value.class && tool.value.class !== realToolClass.value) {
        out.push({ label: 'Wrapper', value: tool.value.class, mono: true });
    }
    if (p.toolInvocationId) {
        out.push({ label: 'Tool Invocation ID', value: p.toolInvocationId, mono: true });
    }
    if (p.time != null) {
        out.push({ label: 'Tool Time', value: `${p.time} ms` });
    }
    if (agent.value.class) {
        out.push({ label: 'Agent', value: agent.value.class, mono: true });
    }

    const budget = toolProps.value.budget?.properties;
    if (budget && (budget.budgetUsd != null || budget.accruedCost != null)) {
        out.push({
            label: 'Budget',
            value: `$${budget.budgetUsd} / spent $${budget.accruedCost}`,
        });
    }

    return out;
});

const argumentsData = computed(() => payload.value.arguments ?? null);
const resultData = computed(() => payload.value.result ?? null);
</script>

<template>
    <div>
        <InfoCard
            title="Tool Call"
            :rows="rows"
        />
        <JsonCard
            v-if="argumentsData"
            title="Arguments"
            :data="argumentsData"
        />
        <JsonCard
            v-if="resultData"
            title="Result"
            :data="resultData"
        />
    </div>
</template>
