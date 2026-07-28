<script setup>
defineProps({
    show: { type: Boolean, default: false },
    label: { type: String, required: true },
    variant: { type: String, default: 'secondary' },
});
</script>

<template>
    <span
        v-if="show"
        class="badge ms-2"
        :class="'badge-' + variant"
    >{{ label }}</span>
</template>
