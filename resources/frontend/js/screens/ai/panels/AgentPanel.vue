<script setup>
import { computed } from 'vue';
import InfoCard from './InfoCard.vue';
import JsonCard from './JsonCard.vue';
import ChatThread from './ChatThread.vue';

const props = defineProps({
    entry: { type: Object, required: true },
});

const content = computed(() => props.entry?.content ?? {});
const payload = computed(() => content.value.payload ?? {});

// Events nest the agent inconsistently (payload.agent, payload.prompt.properties.agent,
// payload.prompt = the agent directly, ...). Recursively locate the first object whose
// class ends in `Agent` (but not `Prompt`) so the panel works regardless of shape.
function findAgent(node, depth = 0) {
    if (!node || typeof node !== 'object' || depth > 8) {
        return null;
    }
    if (Array.isArray(node)) {
        for (const item of node) {
            const found = findAgent(item, depth + 1);
            if (found) {
                return found;
            }
        }
        return null;
    }
    if (typeof node.class === 'string' && /Agent$/.test(node.class) && !/Prompt$/.test(node.class)) {
        return node;
    }
    for (const key of Object.keys(node)) {
        const found = findAgent(node[key], depth + 1);
        if (found) {
            return found;
        }
    }
    return null;
}

const agentObj = computed(() => findAgent(payload.value));
const agentClass = computed(() => agentObj.value?.class ?? null);

// Capabilities registered on the agent. Skip obvious support infra objects.
const tools = computed(() => {
    const props = agentObj.value?.properties ?? {};
    const skip = new Set(['hookRunner', 'budgetTracker']);

    return Object.entries(props)
        .map(([name, def]) => ({ name, class: def?.class ?? null }))
        .filter((t) => t.class && !skip.has(t.name));
});

const budgetProps = computed(
    () =>
        payload.value.budgetTracker?.properties
        ?? agentObj.value?.properties?.budgetTracker?.properties
        ?? null,
);

const budgetRows = computed(() => {
    const b = budgetProps.value;
    if (!b) {
        return [];
    }

    const rows = [];
    if (b.budgetUsd != null) {
        rows.push({ label: 'Budget', value: `$${b.budgetUsd}` });
    }
    if (b.accruedCost != null) {
        rows.push({ label: 'Accrued Cost', value: `$${b.accruedCost}` });
    }
    if (b.inFlightCost != null) {
        rows.push({ label: 'In-flight Cost', value: `$${b.inFlightCost}` });
    }
    if (b.inputTokens != null) {
        rows.push({ label: 'Input Tokens', value: String(b.inputTokens) });
    }
    if (b.outputTokens != null) {
        rows.push({ label: 'Output Tokens', value: String(b.outputTokens) });
    }
    if (b.contextChars != null) {
        rows.push({ label: 'Context Chars', value: String(b.contextChars) });
    }
    if (b.inputCostPerM != null) {
        rows.push({ label: 'Input $/M', value: `$${b.inputCostPerM}` });
    }
    if (b.outputCostPerM != null) {
        rows.push({ label: 'Output $/M', value: `$${b.outputCostPerM}` });
    }
    if (b.cacheReadTokens != null) {
        rows.push({ label: 'Cache Read Tokens', value: String(b.cacheReadTokens) });
    }
    if (b.cacheWriteTokens != null) {
        rows.push({ label: 'Cache Write Tokens', value: String(b.cacheWriteTokens) });
    }
    if (b.explicitPricing != null) {
        rows.push({ label: 'Explicit Pricing', value: b.explicitPricing ? 'Yes' : 'No' });
    }

    return rows;
});

// Agent class is not shown in the top-level attribute table, so it stays here.
const agentRows = computed(() => {
    if (!agentClass.value) {
        return [];
    }
    return [{ label: 'Agent', value: agentClass.value, mono: true }];
});

const responses = computed(() => payload.value.messages ?? null);
const options = computed(() => payload.value.options ?? null);
</script>

<template>
    <div>
        <InfoCard
            v-if="agentRows.length"
            title="Agent"
            :rows="agentRows"
        />
        <InfoCard
            title="Budget"
            :rows="budgetRows"
        />
        <InfoCard
            v-if="tools.length"
            title="Tools"
            :rows="tools.map((t) => ({ label: t.name, value: t.class, mono: true }))"
        />
        <ChatThread :payload="payload" />
        <JsonCard
            v-if="options"
            title="Options"
            :data="options"
        />
    </div>
</template>
