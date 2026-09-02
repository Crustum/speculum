<script setup>
import { computed } from 'vue';
import JsonCard from './JsonCard.vue';

const props = defineProps({
    entry: { type: Object, required: true },
});

const payload = computed(() => props.entry?.content?.payload ?? {});
</script>

<template>
    <div>
        <JsonCard
            title="Prompt"
            :data="payload.prompt?.properties"
        />
        <JsonCard
            title="Response"
            :data="payload.response?.properties"
        />
    </div>
</template>
