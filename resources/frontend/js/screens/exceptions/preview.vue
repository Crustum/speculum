<script setup>
import { ref } from 'vue';
import api from '@/utils/api';
import { useAlert } from '@/composables/useAlert';
import { useTimeAgo } from '@/composables/useTimeAgo';
import { formatFileLocation, editorHref } from '@/utils/projectPath';

const { alertConfirm } = useAlert();
const { localTime, timeAgo } = useTimeAgo();

const currentTab = ref('message');

function hasContext(entryItem) {
    const content = entryItem?.content;
    if (!content || typeof content !== 'object') {
        return false;
    }

    return Object.prototype.hasOwnProperty.call(content, 'context')
        && content.context !== null;
}

function markExceptionAsResolved(entryItem) {
    alertConfirm('Are you sure you want to mark this exception as resolved?', () => {
        api.put('/exceptions/' + entryItem.id, {
            resolved_at: 'now',
        }).then((response) => {
            const updated = response.data.entry;
            Object.assign(entryItem, updated);
        });
    });
}
</script>

<template>
    <preview-screen
        :id="$route.params.id"
        title="Exception Details"
        resource="exceptions"
    >
        <template #table-parameters="slotProps">
            <attribute-row
                title="Type"
                :value="slotProps.entry.content.class"
            />

            <attribute-row
                title="Location"
                code
                :href="editorHref(slotProps.entry.content.file, slotProps.entry.content.line, slotProps.entry.content.editor_url)"
                :value="formatFileLocation(slotProps.entry.content.file, slotProps.entry.content.line)"
            />

            <attribute-row title="Occurrences">
                <router-link
                    :to="{
                        name: 'exceptions',
                        query: { family_hash: slotProps.entry.family_hash },
                    }"
                    class="control-action"
                >
                    View Other Occurrences
                </router-link>
            </attribute-row>

            <attribute-row title="Resolved at">
                <span v-if="slotProps.entry.content.resolved_at">
                    {{ localTime(slotProps.entry.content.resolved_at) }} ({{ timeAgo(slotProps.entry.content.resolved_at) }})
                </span>
                <span v-if="!slotProps.entry.content.resolved_at">
                    <button
                        class="btn btn-sm btn-success"
                        @click.prevent="markExceptionAsResolved(slotProps.entry)"
                    >
                        Mark as resolved
                    </button>
                </span>
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
                            >Message</a>
                        </li>

                        <li class="nav-item">
                            <a
                                class="nav-link"
                                :class="{ active: currentTab == 'location' }"
                                href="#"
                                @click.prevent="currentTab = 'location'"
                            >Location</a>
                        </li>

                        <li class="nav-item">
                            <a
                                v-show="hasContext(slotProps.entry)"
                                class="nav-link"
                                :class="{ active: currentTab == 'context' }"
                                href="#"
                                @click.prevent="currentTab = 'context'"
                            >Context</a>
                        </li>

                        <li class="nav-item">
                            <a
                                class="nav-link"
                                :class="{ active: currentTab == 'trace' }"
                                href="#"
                                @click.prevent="currentTab = 'trace'"
                            >Stacktrace</a>
                        </li>
                    </ul>

                    <div>
                        <pre
                            v-show="currentTab == 'message'"
                            class="code-bg p-4 mb-0 text-white"
                        >{{
                        slotProps.entry.content.message
                    }}</pre>

                        <exception-code-preview
                            v-show="currentTab == 'location'"
                            :lines="slotProps.entry.content.line_preview"
                            :highlighted-line="slotProps.entry.content.line"
                        />

                        <div
                            v-show="currentTab == 'context'"
                            class="code-bg p-4 mb-0 text-white"
                        >
                            <copy-clipboard :data="slotProps.entry.content.context">
                                <vue-json-pretty :data="slotProps.entry.content.context" />
                            </copy-clipboard>
                        </div>

                        <stack-trace
                            v-show="currentTab == 'trace'"
                            :trace="slotProps.entry.content.trace"
                        />
                    </div>
                </div>
            </div>
        </template>
    </preview-screen>
</template>
