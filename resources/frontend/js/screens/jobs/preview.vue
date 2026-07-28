<script setup>
import { ref } from 'vue';
import { useEntryStyles } from '@/composables/useEntryStyles';

const { jobStatusClass } = useEntryStyles();

const currentTab = ref('data');
</script>

<template>
    <preview-screen
        :id="$route.params.id"
        title="Job Details"
        resource="jobs"
        entry-point="true"
    >
        <template #table-parameters="slotProps">
            <attribute-row title="Status">
                <status-badge
                    :label="slotProps.entry.content.status"
                    :variant="jobStatusClass(slotProps.entry.content.status)"
                />
            </attribute-row>

            <attribute-row
                title="Job"
                :value="slotProps.entry.content.name"
            />

            <attribute-row
                title="Connection"
                :value="slotProps.entry.content.connection"
            />

            <attribute-row
                title="Queue"
                :value="slotProps.entry.content.queue"
            />

            <attribute-row
                title="Tries"
                :value="slotProps.entry.content.tries != null ? slotProps.entry.content.tries : '-'"
            />

            <attribute-row
                title="Timeout"
                :value="slotProps.entry.content.timeout != null ? slotProps.entry.content.timeout : '-'"
            />

            <attribute-row title="Duration">
                <status-badge
                    v-if="slotProps.entry.content.slow"
                    :label="slotProps.entry.content.duration + 'ms'"
                    variant="danger"
                />
                <span v-else>{{ slotProps.entry.content.duration != null ? slotProps.entry.content.duration + 'ms' : '-' }}</span>
            </attribute-row>

            <attribute-row
                v-if="slotProps.entry.content.data.batchId"
                title="Batch"
            >
                <router-link
                    :to="{
                        name: 'batch-preview',
                        params: {
                            id: slotProps.entry.content.data.batchId,
                        },
                    }"
                    class="control-action"
                >
                    {{ slotProps.entry.content.data.batchId }}
                </router-link>
            </attribute-row>
        </template>

        <template #after-attributes-card="slotProps">
            <div>
                <div class="card mt-5">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a
                                class="nav-link"
                                :class="{ active: currentTab == 'data' }"
                                href="#"
                                @click.prevent="currentTab = 'data'"
                            >Data</a>
                        </li>
                        <li class="nav-item">
                            <a
                                v-if="slotProps.entry.content.exception"
                                class="nav-link"
                                :class="{ active: currentTab == 'exception' }"
                                href="#"
                                @click.prevent="currentTab = 'exception'"
                            >Exception Message</a>
                        </li>
                        <li class="nav-item">
                            <a
                                v-if="slotProps.entry.content.exception"
                                class="nav-link"
                                :class="{ active: currentTab == 'preview' }"
                                href="#"
                                @click.prevent="currentTab = 'preview'"
                            >Exception Location</a>
                        </li>
                        <li class="nav-item">
                            <a
                                v-if="slotProps.entry.content.exception"
                                class="nav-link"
                                :class="{ active: currentTab == 'trace' }"
                                href="#"
                                @click.prevent="currentTab = 'trace'"
                            >Stacktrace</a>
                        </li>
                    </ul>
                    <div>
                        <div
                            v-show="currentTab == 'data'"
                            class="code-bg p-4 mb-0 text-white"
                        >
                            <copy-clipboard :data="slotProps.entry.content.data">
                                <vue-json-pretty :data="slotProps.entry.content.data" />
                            </copy-clipboard>
                        </div>
                        <pre
                            v-if="slotProps.entry.content.exception"
                            v-show="currentTab == 'exception'"
                            class="code-bg p-4 mb-0 text-white"
                        >{{ slotProps.entry.content.exception.message }}</pre>
                        <stack-trace
                            v-if="slotProps.entry.content.exception"
                            v-show="currentTab == 'trace'"
                            :trace="slotProps.entry.content.exception.trace"
                        />
                        <exception-code-preview
                            v-if="slotProps.entry.content.exception"
                            v-show="currentTab == 'preview'"
                            :lines="slotProps.entry.content.exception.line_preview"
                            :highlighted-line="slotProps.entry.content.exception.line"
                        />
                    </div>
                </div>
            </div>
        </template>
    </preview-screen>
</template>
