<script setup>
import { useEntryStyles } from '@/composables/useEntryStyles';

const { gateResultClass } = useEntryStyles();

const kindBadgeClass = {
    bypass: 'badge-info',
    rule: 'badge-warning',
    bool: 'badge-secondary',
};
</script>

<template>
    <preview-screen
        :id="$route.params.id"
        title="Authorization Details"
        resource="authorization"
    >
        <template #table-parameters="slotProps">
            <attribute-row title="Ability">
                {{ slotProps.entry.content.ability }}
                <span
                    v-if="slotProps.entry.content.link_check"
                    class="badge badge-secondary ms-2"
                >
                    Link
                </span>
            </attribute-row>

            <attribute-row title="Result">
                <span
                    class="badge"
                    :class="'badge-' + gateResultClass(slotProps.entry.content.allowed ? 'allowed' : 'denied')"
                >
                    {{ slotProps.entry.content.allowed ? 'Allowed' : 'Denied' }}
                </span>
                <span
                    v-if="slotProps.entry.content.kind"
                    class="badge ms-2"
                    :class="kindBadgeClass[slotProps.entry.content.kind] || 'badge-secondary'"
                >
                    {{ slotProps.entry.content.kind }}
                </span>
            </attribute-row>

            <attribute-row
                title="Role"
                :value="slotProps.entry.content.role || '-'"
            />

            <attribute-row
                v-if="slotProps.entry.content.policy"
                title="Policy"
                :value="slotProps.entry.content.policy"
                code
            />

            <attribute-row
                v-if="slotProps.entry.content.user_id"
                title="User"
                :value="String(slotProps.entry.content.user_id)"
            />

            <attribute-row
                v-if="slotProps.entry.content.reason"
                title="Reason"
                :value="slotProps.entry.content.reason"
                code
            />
        </template>

        <template #after-attributes-card="slotProps">
            <div
                v-if="slotProps.entry.content.checked"
                class="card mt-3"
            >
                <div class="card-header">
                    <strong>Checked Context</strong>
                </div>
                <div class="code-bg p-4 mb-0 text-white">
                    <copy-clipboard :data="slotProps.entry.content.checked">
                        <vue-json-pretty :data="slotProps.entry.content.checked" />
                    </copy-clipboard>
                </div>
            </div>

            <div
                v-if="slotProps.entry.content.permission"
                class="card mt-3"
            >
                <div class="card-header">
                    <strong>Matched Rule</strong>
                </div>
                <div class="code-bg p-4 mb-0 text-white">
                    <copy-clipboard :data="slotProps.entry.content.permission">
                        <vue-json-pretty :data="slotProps.entry.content.permission" />
                    </copy-clipboard>
                </div>
            </div>

            <div
                v-if="!slotProps.entry.content.checked && slotProps.entry.content.params"
                class="card mt-3"
            >
                <div class="card-header">
                    <strong>Route Params</strong>
                </div>
                <div class="code-bg p-4 mb-0 text-white">
                    <copy-clipboard :data="slotProps.entry.content.params">
                        <vue-json-pretty :data="slotProps.entry.content.params" />
                    </copy-clipboard>
                </div>
            </div>

            <div
                v-if="slotProps.entry.content.link_check_summary"
                class="card mt-3"
            >
                <div class="card-header">
                    <strong>AuthLink Summary</strong>
                </div>
                <div class="code-bg p-4 mb-0 text-white">
                    <copy-clipboard :data="slotProps.entry.content.link_check_summary">
                        <vue-json-pretty :data="slotProps.entry.content.link_check_summary" />
                    </copy-clipboard>
                </div>
            </div>
        </template>
    </preview-screen>
</template>
