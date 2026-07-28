<script setup>
defineProps({
    title: { type: String, required: true },
    value: { type: [String, Number], default: undefined },
    code: { type: Boolean, default: false },
    href: { type: String, default: undefined },
});
</script>

<template>
    <tr>
        <td class="table-fit text-muted">
            {{ title }}
        </td>
        <td>
            <slot>
                <a
                    v-if="href"
                    :href="href"
                    class="control-action"
                >
                    <code v-if="code">{{ value }}</code>
                    <template v-else>{{ value }}</template>
                </a>
                <template v-else>
                    <code v-if="code">{{ value }}</code>
                    <template v-else>
                        {{ value }}
                    </template>
                </template>
            </slot>
        </td>
    </tr>
</template>
