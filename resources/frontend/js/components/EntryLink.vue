<script setup>
import { computed } from 'vue';
import { useHelpers } from '../composables/useHelpers';

const props = defineProps({
    to: { type: [Object, String], required: true },
    text: { type: [String, Number], default: '' },
    limit: { type: Number, default: 70 },
    title: { type: String, default: undefined },
    code: { type: Boolean, default: false },
});

const { truncate } = useHelpers();

const display = computed(() => truncate(props.text || '-', props.limit));
const linkTitle = computed(() => {
    if (props.title !== undefined) {
        return props.title;
    }

    return props.text ? String(props.text) : undefined;
});
</script>

<template>
    <router-link
        :to="to"
        class="entry-link"
        :title="linkTitle"
    >
        <code v-if="code">{{ display }}</code>
        <template v-else>
            {{ display }}
        </template>
    </router-link>
</template>
