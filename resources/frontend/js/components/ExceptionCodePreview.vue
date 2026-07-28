<script setup>
import hljs from 'highlight.js/lib/core';
import php from 'highlight.js/lib/languages/php';

hljs.registerLanguage('php', php);

const props = defineProps({
    lines: {
        type: Object,
        default: () => ({}),
    },
    highlightedLine: {
        type: [Number, String],
        default: null,
    },
});

function highlight(line) {
    return hljs.highlight(String(line ?? ''), { language: 'php' }).value;
}
</script>

<template>
    <pre class="code-bg px-4 mb-0 text-white">
        <p
            v-for="(line, number) in props.lines"
            :key="number"
            class="mb-0"
            :class="{ highlight: Number(number) == Number(props.highlightedLine) }"
        ><span class="me-4">{{ number }}</span> <span v-html="highlight(line)" /></p>
    </pre>
</template>
