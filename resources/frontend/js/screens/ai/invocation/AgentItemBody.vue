<script setup>
import { computed } from 'vue';
import { entryException, summarizeArgs } from '@/utils/aiTool';
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
            v-if="item.responseToolCalls.length"
            class="mb-1 ps-2"
        >
            <div
                v-for="call in item.responseToolCalls"
                :key="call.id ?? call.name"
                class="text-monospace"
                style="word-break: break-word;"
            >
                <code>{{ call.name }}({{ summarizeArgs(call.arguments) }})</code>
                <span
                    v-if="call.id"
                    class="text-muted"
                >(ID: {{ call.id }})</span>
            </div>
        </div>
        <div
            v-if="item.usageLine || item.main.content.step != null"
            class="d-flex align-items-center gap-2"
        >
            <div
                v-if="item.usageLine"
                class="small text-muted me-auto"
            >
                {{ item.usageLine }}
            </div>
            <span
                v-else
                class="me-auto"
            />
            <span
                v-if="item.main.content.step != null"
                class="badge badge-info"
            >step {{ item.main.content.step }}</span>
        </div>
    </div>
</template>
