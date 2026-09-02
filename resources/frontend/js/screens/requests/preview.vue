<script setup>
import { ref } from 'vue';
import { useEntryStyles } from '@/composables/useEntryStyles';

const { requestMethodClass, requestStatusClass } = useEntryStyles();

const currentRequestTab = ref('payload');
const currentResponseTab = ref('response');
</script>

<template>
    <preview-screen
        :id="$route.params.id"
        title="Request Details"
        resource="requests"
        entry-point="true"
    >
        <template #table-parameters="slotProps">
            <attribute-row title="Method">
                <status-badge
                    :label="slotProps.entry.content.method"
                    :variant="requestMethodClass(slotProps.entry.content.method)"
                />
            </attribute-row>

            <attribute-row
                title="Controller Action"
                :value="slotProps.entry.content.controller_action"
            />

            <attribute-row
                v-if="slotProps.entry.content.matched_route"
                title="Matched Route"
                code
                :value="slotProps.entry.content.matched_route"
            />

            <attribute-row
                title="Path"
                :value="slotProps.entry.content.uri"
            />

            <attribute-row title="Status">
                <status-badge
                    :label="slotProps.entry.content.response_status"
                    :variant="requestStatusClass(slotProps.entry.content.response_status)"
                />
            </attribute-row>

            <attribute-row title="Duration">
                <status-badge
                    v-if="slotProps.entry.content.slow"
                    :label="slotProps.entry.content.duration + 'ms'"
                    variant="danger"
                />
                <span v-else>{{ slotProps.entry.content.duration != null ? slotProps.entry.content.duration + 'ms' : '-' }}</span>
            </attribute-row>

            <attribute-row
                title="IP Address"
                :value="slotProps.entry.content.ip_address || '-'"
            />

            <attribute-row title="Memory usage">
                {{ slotProps.entry.content.memory || '-' }} MB
            </attribute-row>
        </template>

        <template #after-attributes-card="slotProps">
            <div>
                <div class="card mt-5 overflow-hidden">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a
                                class="nav-link"
                                :class="{ active: currentRequestTab == 'payload' }"
                                href="#"
                                @click.prevent="currentRequestTab = 'payload'"
                            >Payload</a>
                        </li>
                        <li class="nav-item">
                            <a
                                class="nav-link"
                                :class="{ active: currentRequestTab == 'query' }"
                                href="#"
                                @click.prevent="currentRequestTab = 'query'"
                            >Query</a>
                        </li>
                        <li class="nav-item">
                            <a
                                class="nav-link"
                                :class="{ active: currentRequestTab == 'params' }"
                                href="#"
                                @click.prevent="currentRequestTab = 'params'"
                            >Params</a>
                        </li>
                        <li class="nav-item">
                            <a
                                class="nav-link"
                                :class="{ active: currentRequestTab == 'headers' }"
                                href="#"
                                @click.prevent="currentRequestTab = 'headers'"
                            >Headers</a>
                        </li>
                    </ul>
                    <div class="code-bg p-4 mb-0 text-white">
                        <copy-clipboard :data="slotProps.entry.content[currentRequestTab]">
                            <vue-json-pretty :data="slotProps.entry.content[currentRequestTab]" />
                        </copy-clipboard>
                    </div>
                </div>
                <collapsible-content
                    title="Response body"
                    :collapsed="currentResponseTab === 'response'"
                >
                    <copy-clipboard :data="slotProps.entry.content[currentResponseTab]">
                        <vue-json-pretty :data="slotProps.entry.content[currentResponseTab]" />
                    </copy-clipboard>
                </collapsible-content>
            </div>
        </template>
    </preview-screen>
</template>