<script setup>
import { computed, ref } from 'vue';
import { formatFileLocation, editorHref } from '../utils/projectPath';

const props = defineProps({
    trace: {
        type: Array,
        default: () => [],
    },
});

const minimumLines = 5;
const showAll = ref(false);

const lines = computed(() => {
    const trace = props.trace || [];
    const slice = showAll.value ? trace.slice(0, 1000) : trace.slice(0, minimumLines);

    return slice.map((line) => ({
        ...line,
        href: editorHref(line.file, line.line, line.editor_url),
        label: formatFileLocation(line.file, line.line),
    }));
});
</script>

<template>
    <table class="table mb-0">
        <tbody>
            <tr
                v-for="(line, index) in lines"
                :key="index"
            >
                <td class="card-bg-secondary">
                    <a
                        v-if="line.href"
                        :href="line.href"
                        class="control-action"
                    >
                        <code>{{ line.label }}</code>
                    </a>
                    <code v-else>{{ line.label }}</code>
                </td>
            </tr>

            <tr v-if="!showAll">
                <td class="card-bg-secondary">
                    <a
                        href="#"
                        @click.prevent="showAll = true"
                    >Show All</a>
                </td>
            </tr>
        </tbody>
    </table>
</template>
