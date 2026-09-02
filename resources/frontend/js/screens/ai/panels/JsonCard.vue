<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    data: { type: [Object, Array], default: null },
});

const json = computed(() => {
    try {
        return JSON.stringify(props.data ?? null);
    } catch {
        return '';
    }
});

const size = computed(() => json.value.length);

// Collapse by default once the payload is large enough to dominate the screen.
const COLLAPSE_THRESHOLD = 1500;
</script>

<template>
    <div
        v-if="data != null"
        class="card mt-5 overflow-hidden"
    >
        <collapsible-content
            :title="title + (size ? ` (${size} chars)` : '')"
            :collapsed="size > COLLAPSE_THRESHOLD"
        >
            <copy-clipboard :data="data">
                <vue-json-pretty :data="data" />
            </copy-clipboard>
        </collapsible-content>
    </div>
</template>
