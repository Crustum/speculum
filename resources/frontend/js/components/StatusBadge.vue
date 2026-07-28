<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: [String, Number], required: true },
    variant: { type: String, default: 'secondary' },
    size: { type: String, default: undefined },
});

const classes = computed(() => {
    const list = ['badge', `badge-${props.variant}`];

    if (props.size === 'sm') {
        list.push('badge-sm');
    }

    return list;
});
</script>

<template>
    <span :class="classes">
        <slot>{{ label }}</slot>
    </span>
</template>
