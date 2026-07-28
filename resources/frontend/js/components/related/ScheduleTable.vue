<script setup>
defineProps({
    items: { type: Array, default: () => [] },
});
</script>

<template>
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Command</th>
                <th>Expression</th>
                <th />
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="item in items"
                :key="item.id"
            >
                <td>
                    <entry-link
                        :to="{ name: 'schedule-preview', params: { id: item.id } }"
                        :text="item.content.description || item.content.command"
                        :limit="85"
                        code
                    />
                </td>
                <muted-text-cell
                    fit
                    :text="item.content.expression"
                    :limit="200"
                />
                <view-link-cell :to="{ name: 'schedule-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
