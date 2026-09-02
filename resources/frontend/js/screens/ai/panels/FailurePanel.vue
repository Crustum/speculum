<script setup>
import { computed } from 'vue';
import InfoCard from './InfoCard.vue';
import JsonCard from './JsonCard.vue';

const props = defineProps({
    entry: { type: Object, required: true },
});

const content = computed(() => props.entry?.content ?? {});
const payload = computed(() => props.entry?.content?.payload ?? {});

const rows = computed(() => {
    const ex = content.value.exception;
    if (!ex) {
        return [];
    }

    return [
        { label: 'Class', value: ex.class, mono: true },
        { label: 'Message', value: ex.message },
    ];
});
</script>

<template>
    <div>
        <InfoCard
            title="Failure"
            :rows="rows"
        />
        <JsonCard
            title="Exception Data"
            :data="payload.exception"
        />
    </div>
</template>
