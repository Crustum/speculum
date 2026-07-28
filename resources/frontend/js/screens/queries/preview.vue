<script setup>
import { nextTick, ref } from 'vue';
import hljs from 'highlight.js/lib/core';
import sql from 'highlight.js/lib/languages/sql';
import { formatSql } from '../../utils/formatSql';
import { formatFileLocation, editorHref } from '../../utils/projectPath';

hljs.registerLanguage('sql', sql);

const sqlcode = ref(null);
const currentTab = ref('query');

function highlightSQL() {
    nextTick(() => {
        if (!sqlcode.value || currentTab.value !== 'query') {
            return;
        }

        try {
            hljs.highlightElement(sqlcode.value);
        } catch (_e) {
        }
    });
}
</script>

<template>
    <preview-screen
        :id="$route.params.id"
        title="Query Details"
        resource="queries"
        @ready="highlightSQL()"
    >
        <template #table-parameters="slotProps">
            <attribute-row
                title="Connection"
                :value="slotProps.entry.content.connection"
            />

            <attribute-row
                v-if="slotProps.entry.content.file"
                title="Location"
                code
                :href="editorHref(slotProps.entry.content.file, slotProps.entry.content.line, slotProps.entry.content.editor_url)"
                :value="formatFileLocation(slotProps.entry.content.file, slotProps.entry.content.line)"
            />

            <attribute-row title="Duration">
                <span
                    v-if="slotProps.entry.content.slow"
                    class="badge badge-danger"
                >
                    {{ slotProps.entry.content.time }}ms
                </span>

                <span v-else> {{ slotProps.entry.content.time }}ms </span>
            </attribute-row>
        </template>

        <template #after-attributes-card="slotProps">
            <div>
                <div class="card mt-5 overflow-hidden">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a
                                class="nav-link"
                                :class="{ active: currentTab == 'query' }"
                                href="#"
                                @click.prevent="currentTab = 'query'; highlightSQL()"
                            >Query</a>
                        </li>
                        <li class="nav-item">
                            <a
                                class="nav-link"
                                :class="{ active: currentTab == 'bindings' }"
                                href="#"
                                @click.prevent="currentTab = 'bindings'"
                            >Bindings</a>
                        </li>
                    </ul>
                    <div
                        v-show="currentTab == 'query'"
                        class="code-bg p-4 mb-0 text-white"
                    >
                        <copy-clipboard :data="formatSql(slotProps.entry.content.sql, slotProps.entry.content.driver)">
                            <pre
                                ref="sqlcode"
                                class="code-bg text-white"
                            >{{ formatSql(slotProps.entry.content.sql, slotProps.entry.content.driver) }}</pre>
                        </copy-clipboard>
                    </div>
                    <div
                        v-show="currentTab == 'bindings'"
                        class="code-bg p-4 mb-0 text-white"
                    >
                        <copy-clipboard :data="slotProps.entry.content.bindings || []">
                            <vue-json-pretty :data="slotProps.entry.content.bindings || []" />
                        </copy-clipboard>
                    </div>
                </div>
            </div>
        </template>
    </preview-screen>
</template>
