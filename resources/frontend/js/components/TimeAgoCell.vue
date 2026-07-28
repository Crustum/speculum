<script setup>
import { useTimeAgo } from '../composables/useTimeAgo';

defineProps({
    value: { type: [String, Number, Date], required: true },
});

const { timeAgo } = useTimeAgo();
</script>

<template>
    <td
        class="table-fit text-muted"
        :data-timeago="value"
        :title="value"
    >
        {{ timeAgo(value) }}
    </td>
</template>
