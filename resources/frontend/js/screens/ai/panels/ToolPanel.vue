<script setup>
import { computed } from 'vue';
import InfoCard from './InfoCard.vue';
import JsonCard from './JsonCard.vue';
import {
    innerToolClass,
    resultText,
    summarizeValue,
    toolCallLine,
    toolDisplayName,
    toolInvocationId,
    toolWrapperName,
} from '@/utils/aiTool';

const props = defineProps({
    entry: { type: Object, required: true },
});

const content = computed(() => props.entry?.content ?? {});
const payload = computed(() => content.value.payload ?? {});

const agent = computed(() => payload.value.agent ?? {});

const displayName = computed(() => toolDisplayName(props.entry));
const innerClass = computed(() => innerToolClass(props.entry));
const wrapper = computed(() => toolWrapperName(props.entry));
const invocationId = computed(() => toolInvocationId(props.entry));
const callLine = computed(() => toolCallLine(props.entry));

// Invocation ID, Duration and Summary are already in the top-level table, so the
// Tool Call card only shows what is specific to the tool invocation itself.
const rows = computed(() => {
    const p = payload.value;
    const out = [];

    if (displayName.value) {
        out.push({ label: 'Tool', value: displayName.value, mono: true });
    }
    if (innerClass.value && innerClass.value !== displayName.value) {
        out.push({ label: 'Class', value: innerClass.value, mono: true });
    }
    if (wrapper.value) {
        out.push({ label: 'Wrapper', value: wrapper.value, mono: true });
    }
    if (invocationId.value) {
        out.push({ label: 'Tool Invocation ID', value: invocationId.value, mono: true });
    }
    if (p.time != null) {
        out.push({ label: 'Tool Time', value: `${p.time} ms` });
    }
    if (agent.value.class) {
        out.push({ label: 'Agent', value: agent.value.class, mono: true });
    }

    const budget = payload.value.tool?.properties?.budget?.properties;
    if (budget && (budget.budgetUsd != null || budget.accruedCost != null)) {
        out.push({
            label: 'Budget',
            value: `$${budget.budgetUsd} / spent $${budget.accruedCost}`,
        });
    }

    return out;
});

const argumentEntries = computed(() => {
    const args = payload.value.arguments;

    if (!args || typeof args !== 'object' || Array.isArray(args)) {
        return [];
    }

    return Object.entries(args).map(([key, value]) => ({
        label: key,
        value: summarizeValue(value, 300),
        mono: true,
    }));
});

const argumentsData = computed(() => payload.value.arguments ?? null);
const result = computed(() => resultText(props.entry));
const resultData = computed(() => payload.value.result ?? null);
const showResultJson = computed(() => resultData.value != null && result.value === null);
</script>

<template>
    <div>
        <InfoCard
            title="Tool Call"
            :rows="rows"
        />
        <div
            v-if="callLine"
            class="card mt-5 overflow-hidden"
        >
            <div class="card-header">
                Call
            </div>
            <div class="card-body">
                <code style="white-space: pre-wrap; word-break: break-word;">{{ callLine }}</code>
            </div>
        </div>
        <InfoCard
            v-if="argumentEntries.length"
            title="Arguments"
            :rows="argumentEntries"
        />
        <JsonCard
            v-if="argumentsData"
            title="Arguments (full)"
            :data="argumentsData"
        />
        <div
            v-if="result !== null"
            class="card mt-5 overflow-hidden"
        >
            <div class="card-header">
                Result
            </div>
            <div
                class="card-body"
                style="white-space: pre-wrap; word-break: break-word;"
            >
                {{ result }}
            </div>
        </div>
        <JsonCard
            v-if="showResultJson"
            title="Result"
            :data="resultData"
        />
    </div>
</template>
