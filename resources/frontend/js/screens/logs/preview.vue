<script setup>
import { ref } from 'vue';
import { useEntryStyles } from '@/composables/useEntryStyles';

const { logLevelClass } = useEntryStyles();

const currentTab = ref('message');
</script>

<template>
    <preview-screen
        :id="$route.params.id"
        title="Log Details"
        resource="logs"
    >
        <template #table-parameters="slotProps">
            <attribute-row title="Level">
                <status-badge
                    :label="slotProps.entry.content.level"
                    :variant="logLevelClass(slotProps.entry.content.level)"
                />
            </attribute-row>
        </template>

        <template #after-attributes-card="slotProps">
            <div class="mt-5">
                <div class="card mt-5 overflow-hidden">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a
                                class="nav-link"
                                :class="{ active: currentTab == 'message' }"
                                href="#"
                                @click.prevent="currentTab = 'message'"
                            >Log Message</a>
                        </li>
                        <li class="nav-item">
                            <a
                                class="nav-link"
                                :class="{ active: currentTab == 'context' }"
                                href="#"
                                @click.prevent="currentTab = 'context'"
                            >Context</a>
                        </li>
                    </ul>
                    <div>
                        <!-- Log Message -->
                        <div v-show="currentTab == 'message'">
                            <copy-clipboard :data="slotProps.entry.content.message">
                                <pre class="code-bg p-4 mb-0 text-white">{{ slotProps.entry.content.message }}</pre>
                            </copy-clipboard>
                        </div>

                        <!-- Context -->
                        <div
                            v-show="currentTab == 'context'"
                            class="code-bg p-4 mb-0 text-white"
                        >
                            <copy-clipboard :data="slotProps.entry.content.context">
                                <vue-json-pretty :data="slotProps.entry.content.context" />
                            </copy-clipboard>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </preview-screen>
</template>
