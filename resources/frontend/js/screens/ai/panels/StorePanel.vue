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
    if (c.store_id) {
        out.push({ label: 'Store ID', value: c.store_id, mono: true });
    }
    if (c.store_name) {
        out.push({ label: 'Store Name', value: c.store_name });
    }
    if (c.file_id) {
        out.push({ label: 'File ID', value: c.file_id, mono: true });
    }
    if (c.document_id) {
        out.push({ label: 'Document ID', value: c.document_id, mono: true });
    }

    return out;
});
</script>

<template>
    <div>
        <InfoCard
            title="Store / File"
            :rows="rows"
        />
        <JsonCard
            title="File"
            :data="payload.file?.properties"
        />
        <JsonCard
            title="Store"
            :data="payload.store?.properties"
        />
    </div>
</template>
