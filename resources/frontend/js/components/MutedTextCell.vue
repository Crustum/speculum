<script setup>
import { computed } from 'vue';
import { useHelpers } from '../composables/useHelpers';

const props = defineProps({
    text: { type: [String, Number], default: '' },
    limit: { type: Number, default: 60 },
    fit: { type: Boolean, default: false },
});

const { truncate } = useHelpers();

const display = computed(() => truncate(props.text || '-', props.limit));
const cellClass = computed(() => (props.fit ? 'table-fit text-muted' : 'text-muted'));
</script>

<template>
    <td :class="cellClass">
        {{ display }}
    </td>
</template>
