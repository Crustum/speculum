<script setup>
import { ref } from 'vue';

const currentTab = ref('request');

function isWrite(operation) {
    return operation === 'update' || operation === 'delete';
}

function hasPayload(entry) {
    return !!(entry.content.request || entry.content.response);
}

function ensureTab(entry) {
    if (currentTab.value === 'request' && !entry.content.request && entry.content.response) {
        currentTab.value = 'response';
    }

    if (currentTab.value === 'response' && !entry.content.response && entry.content.request) {
        currentTab.value = 'request';
    }
}
</script>

<template>
    <preview-screen
        :id="$route.params.id"
        title="Searches Details"
        resource="searches"
        @update:entry="ensureTab"
    >
        <template #table-parameters="slotProps">
            <attribute-row
                title="Operation"
                :value="slotProps.entry.content.operation || 'search'"
            />

            <attribute-row
                v-if="!isWrite(slotProps.entry.content.operation)"
                title="Query"
                :value="slotProps.entry.content.query"
                code
            />

            <attribute-row
                v-if="slotProps.entry.content.engine"
                title="Engine"
                :value="slotProps.entry.content.engine"
            />

            <attribute-row
                v-if="slotProps.entry.content.index"
                title="Index"
                :value="slotProps.entry.content.index"
            />

            <attribute-row
                v-if="slotProps.entry.content.table"
                title="Table"
                :value="slotProps.entry.content.table"
            />

            <attribute-row
                v-if="!isWrite(slotProps.entry.content.operation)"
                title="Hits"
                :value="slotProps.entry.content.hits ?? '—'"
            />

            <attribute-row
                v-if="isWrite(slotProps.entry.content.operation)"
                title="Documents"
                :value="slotProps.entry.content.count ?? '—'"
            />

            <attribute-row
                v-if="slotProps.entry.content.page != null"
                title="Page"
                :value="String(slotProps.entry.content.page)"
            />

            <attribute-row
                v-if="slotProps.entry.content.per_page != null"
                title="Per page"
                :value="String(slotProps.entry.content.per_page)"
            />

            <attribute-row title="Duration">
                <span
                    v-if="slotProps.entry.content.slow"
                    class="badge badge-danger"
                >
                    {{ slotProps.entry.content.time }}ms
                </span>
                <span v-else>{{ slotProps.entry.content.time }}ms</span>
            </attribute-row>
        </template>

        <template #after-attributes-card="slotProps">
            <div v-if="hasPayload(slotProps.entry)">
                <div class="card mt-5 overflow-hidden">
                    <ul class="nav nav-pills">
                        <li
                            v-if="slotProps.entry.content.request"
                            class="nav-item"
                        >
                            <a
                                class="nav-link"
                                :class="{ active: currentTab == 'request' }"
                                href="#"
                                @click.prevent="currentTab = 'request'"
                            >Request</a>
                        </li>
                        <li
                            v-if="slotProps.entry.content.response"
                            class="nav-item"
                        >
                            <a
                                class="nav-link"
                                :class="{ active: currentTab == 'response' }"
                                href="#"
                                @click.prevent="currentTab = 'response'"
                            >Response</a>
                        </li>
                    </ul>
                    <div
                        v-show="currentTab == 'request' && slotProps.entry.content.request"
                        class="code-bg p-4 mb-0 text-white"
                    >
                        <copy-clipboard :data="slotProps.entry.content.request">
                            <vue-json-pretty :data="slotProps.entry.content.request" />
                        </copy-clipboard>
                    </div>
                    <div
                        v-show="currentTab == 'response' && slotProps.entry.content.response"
                        class="code-bg p-4 mb-0 text-white"
                    >
                        <copy-clipboard :data="slotProps.entry.content.response">
                            <vue-json-pretty :data="slotProps.entry.content.response" />
                        </copy-clipboard>
                    </div>
                </div>
            </div>
        </template>
    </preview-screen>
</template>
