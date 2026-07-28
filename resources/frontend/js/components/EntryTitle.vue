<script setup>
defineProps({
    to: { type: [Object, String], required: true },
    text: { type: [String, Number], default: '' },
    limit: { type: Number, default: 70 },
    title: { type: String, default: undefined },
    code: { type: Boolean, default: false },
    subtitle: { type: String, default: undefined },
    subtitleHref: { type: String, default: undefined },
});
</script>

<template>
    <entry-link
        :to="to"
        :text="text"
        :limit="limit"
        :title="title"
        :code="code"
    />
    <slot name="flags" />
    <template v-if="subtitle || $slots.subtitle">
        <br>
        <small class="text-muted">
            <slot name="subtitle">
                <a
                    v-if="subtitleHref"
                    :href="subtitleHref"
                    class="control-action text-muted"
                >{{ subtitle }}</a>
                <template v-else>{{ subtitle }}</template>
            </slot>
        </small>
    </template>
</template>
