<script setup>
import { ref } from 'vue';

const currentTab = ref('arguments');
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
            <div>
                <div class="card mt-5 overflow-hidden">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a
                                class="nav-link"
                                :class="{ active: currentTab == 'arguments' }"
                                href="#"
                                @click.prevent="currentTab = 'arguments'"
                            >Arguments</a>
                        </li>
                        <li class="nav-item">
                            <a
                                class="nav-link"
                                :class="{ active: currentTab == 'options' }"
                                href="#"
                                @click.prevent="currentTab = 'options'"
                            >Options</a>
                        </li>
                    </ul>
                    <div>
                        <div class="code-bg p-4 mb-0 text-white">
                            <copy-clipboard :data="slotProps.entry.content[currentTab]">
                                <vue-json-pretty :data="slotProps.entry.content[currentTab]" />
                            </copy-clipboard>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </preview-screen>
</template>
