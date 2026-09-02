<script setup>
import CommandDetailsTabs from '../../commands/CommandDetailsTabs.vue';
</script>

<template>
    <preview-screen
        :id="$route.params.id"
        title="Command Details"
        resource="commands"
        entry-point="true"
    >
        <template #table-parameters="slotProps">
            <attribute-row
                title="Command"
                code
                :value="slotProps.entry.content.command"
            />

            <attribute-row
                title="Exit Code"
                :value="slotProps.entry.content.exit_code"
            />

            <attribute-row title="Duration">
                <status-badge
                    v-if="slotProps.entry.content.slow"
                    :label="slotProps.entry.content.duration + 'ms'"
                    variant="danger"
                />
                <span v-else>{{ slotProps.entry.content.duration != null ? slotProps.entry.content.duration + 'ms' : '-' }}</span>
            </attribute-row>
        </template>

        <template #after-attributes-card="slotProps">
            <CommandDetailsTabs :entry="slotProps.entry" />
        </template>
    </preview-screen>
</template>
