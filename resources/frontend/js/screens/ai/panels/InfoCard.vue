<script setup>
defineProps({
    title: { type: String, default: 'Details' },
    rows: { type: Array, default: () => [] },
});
</script>

<template>
    <div
        v-if="rows.length"
        class="card mt-5 overflow-hidden"
    >
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">
                {{ title }}
            </h5>
        </div>
        <table class="table mb-0 card-bg-secondary table-borderless">
            <tbody>
                <tr
                    v-for="row in rows"
                    :key="row.label"
                >
                    <td class="table-fit text-muted align-middle">
                        {{ row.label }}
                    </td>
                    <td class="align-middle">
                        <span
                            v-if="row.mono"
                            class="text-monospace"
                        >{{ row.value }}</span>
                        <span v-else>{{ row.value }}</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
