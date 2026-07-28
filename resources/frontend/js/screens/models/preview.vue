<script setup>
import { useEntryStyles } from '@/composables/useEntryStyles';

const { modelActionClass } = useEntryStyles();
</script>

<template>
    <preview-screen
        :id="$route.params.id"
        title="Model Action"
        resource="models"
    >
        <template #table-parameters="slotProps">
            <attribute-row
                title="Model"
                :value="slotProps.entry.content.model"
            />

            <attribute-row title="Action">
                <status-badge
                    :label="slotProps.entry.content.action"
                    :variant="modelActionClass(slotProps.entry.content.action)"
                />
            </attribute-row>

            <attribute-row
                v-if="slotProps.entry.content.count"
                title="Hydrated"
                :value="slotProps.entry.content.count"
            />
        </template>

        <template #after-attributes-card="slotProps">
            <div>
                <div
                    v-if="slotProps.entry.content.action != 'deleted' && slotProps.entry.content.changes"
                    class="card mt-5 overflow-hidden"
                >
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a class="nav-link active">Changes</a>
                        </li>
                    </ul>

                    <div class="code-bg p-4 mb-0 text-white">
                        <copy-clipboard :data="slotProps.entry.content.changes">
                            <vue-json-pretty :data="slotProps.entry.content.changes" />
                        </copy-clipboard>
                    </div>
                </div>
            </div>
        </template>
    </preview-screen>
</template>
