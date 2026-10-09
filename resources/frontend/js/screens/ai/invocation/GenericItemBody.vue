<script setup>
import { computed } from 'vue';
import { entryException, historyLine } from '@/utils/aiTool';
import CollapsibleBlock from './CollapsibleBlock.vue';

const props = defineProps({
    item: { type: Object, required: true },
});

const errorLine = computed(() => entryException(props.item.main));

function capitalize(value) {
    const text = String(value ?? '');

    return text.charAt(0).toUpperCase() + text.slice(1);
}
</script>

<template>
    <div>
        <CollapsibleBlock
            v-if="errorLine"
            title="Error"
            :preview="errorLine"
        >
            <div
                class="text-muted"
                style="white-space: pre-wrap; word-break: break-word;"
            >
                {{ errorLine }}
            </div>
        </CollapsibleBlock>
        <ol
            v-if="item.newMessages.length"
            class="mb-2 ps-4"
        >
            <li
                v-for="(message, i) in item.newMessages"
                :key="i"
                class="mb-1"
            >
                <strong>{{ capitalize(message.role) }}:</strong>
                <span style="white-space: pre-wrap;">{{ message.content }}</span>
            </li>
        </ol>
        <div
            v-if="item.responseText"
            class="p-2 rounded card-bg-secondary border mb-1"
            style="white-space: pre-wrap;"
        >
            {{ item.responseText }}
        </div>
        <div
            v-if="item.embeddingsLine"
            class="small text-monospace text-muted mb-1 ps-2"
        >
            embeddings: {{ item.embeddingsLine }}
        </div>
        <div
            v-if="item.usageLine"
            class="small text-muted"
        >
            {{ item.usageLine }}
        </div>
        <div
            v-if="!errorLine && !item.newMessages.length && !item.responseText && !item.embeddingsLine && !item.usageLine"
            class="text-muted ps-2"
        >
            {{ historyLine(item.entry) }}
        </div>
    </div>
</template>
