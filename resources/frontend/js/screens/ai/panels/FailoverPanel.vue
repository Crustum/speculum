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
    const c = content.value;
    const out = [];
    if (c.from) {
        out.push({ label: 'From', value: c.from, mono: true });
    }
    if (c.to) {
        out.push({ label: 'To', value: c.to, mono: true });
    }

    return out;
});
</script>

<template>
    <div>
        <InfoCard
            title="Failover"
            :rows="rows"
        />
        <JsonCard
            title="Exception"
            :data="payload.exception"
        />
    </div>
</template>
