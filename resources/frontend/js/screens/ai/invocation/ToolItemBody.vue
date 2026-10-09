<script setup>
import { computed } from 'vue';
import {
    entryArguments,
    resultText,
    summarizeValue,
    toolCallLine,
} from '@/utils/aiTool';
import CollapsibleBlock from './CollapsibleBlock.vue';

const props = defineProps({
    item: { type: Object, required: true },
});

const args = computed(() => entryArguments(props.item.entry));
const argKeys = computed(() => Object.keys(args.value));

const resultLine = computed(() => {
    const exception = props.item.main.content.exception;

    if (exception) {
        return `${exception.class ?? 'error'}: ${exception.message ?? ''}`;
    }

    const text = resultText(props.item.entry);

    return typeof text === 'string' && text.trim() !== '' ? text : null;
});

const resultTitle = computed(() => {
    if (props.item.main.content.exception) {
        return 'Error';
    }

    return resultLine.value ? `Result (${resultLine.value.length} chars)` : 'Result';
});
</script>

<template>
    <div>
        <div
            class="text-monospace mb-2 ps-2"
            style="word-break: break-word;"
        >
            <code>{{ toolCallLine(item.entry) }}</code>
        </div>
        <CollapsibleBlock
            v-if="argKeys.length"
            :title="`Arguments (${argKeys.length})`"
        >
            <div
                v-for="key in argKeys"
                :key="key"
                class="d-flex gap-3 mb-1"
            >
                <span class="text-muted flex-shrink-0">{{ key }}</span>
                <span style="white-space: pre-wrap; word-break: break-word;">{{ summarizeValue(args[key], 2000) }}</span>
            </div>
        </CollapsibleBlock>
        <CollapsibleBlock
            v-if="resultLine"
            :title="resultTitle"
            :preview="resultLine"
        >
            <div
                class="text-muted"
                style="white-space: pre-wrap; word-break: break-word;"
            >
                {{ resultLine }}
            </div>
        </CollapsibleBlock>
        <div
            v-if="item.main.content.duration != null"
            class="small text-muted"
        >
            {{ item.main.content.duration }}ms
        </div>
    </div>
</template>
