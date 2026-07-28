<script setup>
import { useEntryStyles } from '@/composables/useEntryStyles';

const { cacheActionTypeClass } = useEntryStyles();

function formatExpiration(expiration) {
    return expiration + ' seconds';
}
</script>

<template>
    <preview-screen
        :id="$route.params.id"
        title="Cache Details"
        resource="cache"
    >
        <template #table-parameters="slotProps">
            <attribute-row title="Action">
                <status-badge
                    :label="slotProps.entry.content.type"
                    :variant="cacheActionTypeClass(slotProps.entry.content.type)"
                />
            </attribute-row>

            <attribute-row
                title="Key"
                :value="slotProps.entry.content.key"
            />

            <attribute-row
                v-if="slotProps.entry.content.expiration"
                title="Expiration"
                :value="formatExpiration(slotProps.entry.content.expiration)"
            />
        </template>

        <template #after-attributes-card="slotProps">
            <div>
                <div
                    v-if="slotProps.entry.content.value"
                    class="card mt-5 overflow-hidden"
                >
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a class="nav-link active">Value</a>
                        </li>
                    </ul>

                    <pre class="code-bg p-4 mb-0 text-white">{{ slotProps.entry.content.value }}</pre>
                </div>
            </div>
        </template>
    </preview-screen>
</template>
