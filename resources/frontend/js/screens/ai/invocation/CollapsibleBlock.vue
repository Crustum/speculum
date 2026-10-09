<script setup>
import { ref } from 'vue';

// Theme-aware collapsible block (unlike the global collapsible-content with
// its fixed black code background): header card + card-bg-secondary body,
// readable in both light and dark modes.
const props = defineProps({
    title: { type: String, default: '' },
    collapsed: { type: Boolean, default: true },
    // Optional text preview shown clamped to a few lines while collapsed.
    preview: { type: String, default: '' },
});

const expanded = ref(!props.collapsed);

function toggle() {
    expanded.value = !expanded.value;
}
</script>

<template>
    <div class="card mb-2 overflow-hidden">
        <div class="px-3 py-2 border-bottom d-flex align-items-center justify-content-between">
            <span class="text-muted small">{{ title }}</span>
            <button
                type="button"
                class="btn btn-sm btn-muted"
                @click="toggle"
            >
                {{ expanded ? 'Collapse' : 'Show All' }}
            </button>
        </div>
        <div
            v-if="expanded"
            class="p-3 card-bg-secondary"
        >
            <slot />
        </div>
        <div
            v-else-if="preview"
            class="p-3 card-bg-secondary text-muted"
            :title="preview"
            style="display: -webkit-box; -webkit-line-clamp: 5; -webkit-box-orient: vertical; overflow: hidden; white-space: pre-wrap; word-break: break-word;"
        >
            {{ preview }}
        </div>
    </div>
</template>
