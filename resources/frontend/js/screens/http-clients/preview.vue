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
        title="HTTP Client Request Details"
        resource="http-clients"
    >
        <template #table-parameters="slotProps">
            <attribute-row title="Method">
                <status-badge
                    :label="slotProps.entry.content.method"
                    :variant="requestMethodClass(slotProps.entry.content.method)"
                />
            </attribute-row>

            <attribute-row
                title="URI"
                :value="slotProps.entry.content.uri"
            />

            <attribute-row title="Status">
                <status-badge
                    :label="
                        slotProps.entry.content.response_status !== undefined
                            ? slotProps.entry.content.response_status
                            : 'N/A'
                    "
                    :variant="
                        requestStatusClass(
                            slotProps.entry.content.response_status !== undefined
                                ? slotProps.entry.content.response_status
                                : null
                        )
                    "
                />
            </attribute-row>

            <attribute-row title="Duration">
                {{ slotProps.entry.content.duration || '-' }}ms
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
                <div
                    v-if="slotProps.entry.content.response_status"
                    class="card mt-5 overflow-hidden"
                >
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a
                                class="nav-link"
                                :class="{
                                    active: currentResponseTab == 'response',
                                }"
                                href="#"
                                @click.prevent="currentResponseTab = 'response'"
                            >Response</a>
                        </li>
                        <li class="nav-item">
                            <a
                                class="nav-link"
                                :class="{ active: currentResponseTab == 'headers' }"
                                href="#"
                                @click.prevent="currentResponseTab = 'headers'"
                            >Headers</a>
                        </li>
                    </ul>
                    <div class="code-bg p-4 mb-0 text-white">
                        <template v-if="currentResponseTab == 'response'">
                            <copy-clipboard :data="slotProps.entry.content.response">
                                <vue-json-pretty :data="slotProps.entry.content.response" />
                            </copy-clipboard>
                        </template>
                        <template v-if="currentResponseTab == 'headers'">
                            <copy-clipboard :data="slotProps.entry.content.response_headers">
                                <vue-json-pretty :data="slotProps.entry.content.response_headers" />
                            </copy-clipboard>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </preview-screen>
</template>
