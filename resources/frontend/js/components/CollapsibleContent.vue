<script setup>
import { ref } from 'vue';

const props = defineProps({
    // Optional label shown on the left of the toggle bar.
    title: { type: String, default: '' },
    // Start clipped (collapsed) when true; user can expand with "Show All".
    collapsed: { type: Boolean, default: true },
});

const expanded = ref(!props.collapsed);

function toggle() {
    expanded.value = !expanded.value;
}
</script>

<template>
    <div>
        <div class="px-4 py-3 border-bottom d-flex align-items-center justify-content-between">
            <span class="text-muted small">{{ title }}</span>
            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                @click="toggle"
            >
                {{ expanded ? 'Collapse' : 'Show All' }}
            </button>
        </div>
        <div
            class="code-bg p-4 mb-0 text-white"
            :class="{ 'response-body-preview': !expanded }"
        >
            <slot />
        </div>
    </div>
</template>