<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import hljs from 'highlight.js/lib/core';
import php from 'highlight.js/lib/languages/php';

hljs.registerLanguage('php', php);

const props = defineProps({
    result: {
        type: Object,
        required: true,
    },
});

const phpBlocks = ref([]);

const sections = computed(() => {
    if (props.result?.kind === 'sections' && Array.isArray(props.result.sections)) {
        return props.result.sections;
    }

    return [
        {
            kind: props.result?.kind || 'json',
            data: props.result?.data,
            copy: props.result?.copy,
            label: props.result?.label,
        },
    ];
});

const copyPayload = computed(() => {
    if (props.result?.copy !== undefined) {
        return props.result.copy;
    }

    if (props.result?.kind === 'sections') {
        return sections.value.map((section) => section.copy ?? section.data);
    }

    return props.result?.data;
});

function highlightPhp() {
    nextTick(() => {
        phpBlocks.value.forEach((el) => {
            if (!el) {
                return;
            }

            try {
                el.removeAttribute('data-highlighted');
                hljs.highlightElement(el);
            } catch (_error) {
            }
        });
    });
}

watch(
    () => props.result,
    () => highlightPhp(),
    { deep: true, immediate: true },
);

function setPhpRef(el, index) {
    if (el) {
        phpBlocks.value[index] = el;
    }
}
</script>

<template>
    <copy-clipboard :data="copyPayload">
        <div
            v-for="(section, index) in sections"
            :key="index"
            :class="{ 'mt-4': index > 0 }"
        >
            <div
                v-if="section.label"
                class="text-muted small mb-2"
            >
                {{ section.label }}
            </div>

            <vue-json-pretty
                v-if="section.kind === 'json'"
                :data="section.data"
            />

            <pre
                v-else-if="section.kind === 'php'"
                :ref="(el) => setPhpRef(el, index)"
                class="code-bg text-white mb-0"
            >{{ section.data }}</pre>

            <pre
                v-else
                class="code-bg text-white mb-0"
            >{{ typeof section.data === 'string' ? section.data : JSON.stringify(section.data, null, 2) }}</pre>
        </div>
    </copy-clipboard>
</template>
