<script setup>
import { ref } from 'vue';
import { formatFileLocation, editorHref } from '../../utils/projectPath';

const currentTab = ref(0);

function varDumpHtmls(entry) {
    if (Array.isArray(entry?.content?.vardumps) && entry.content.vardumps.length) {
        return entry.content.vardumps;
    }

    if (typeof entry?.content?.vardump === 'string' && entry.content.vardump !== '') {
        return [entry.content.vardump];
    }

    return [];
}

function tabLabel(index, total) {
    return total > 1 ? `Var Dump ${index + 1}` : 'Var Dump';
}
</script>

<template>
    <preview-screen
        :id="$route.params.id"
        title="Var Dump Details"
        resource="vardumps"
    >
        <template #table-parameters="slotProps">
            <attribute-row
                v-if="slotProps.entry.content.summary"
                title="Summary"
                :value="slotProps.entry.content.summary"
            />
            <attribute-row
                v-if="slotProps.entry.content.file"
                title="File"
                code
                :href="editorHref(slotProps.entry.content.file, slotProps.entry.content.line, slotProps.entry.content.editor_url)"
                :value="formatFileLocation(slotProps.entry.content.file, slotProps.entry.content.line)"
            />
            <attribute-row
                v-if="slotProps.entry.content.entry_point_description"
                title="Entry Point"
                :value="slotProps.entry.content.entry_point_description"
            />
            <attribute-row
                v-if="slotProps.entry.content.entry_point_type"
                title="Entry Point Type"
                :value="slotProps.entry.content.entry_point_type"
            />
        </template>

        <template #after-attributes-card="slotProps">
            <div
                v-if="varDumpHtmls(slotProps.entry).length"
                class="mt-5"
            >
                <div class="card mt-5 overflow-hidden">
                    <ul class="nav nav-pills">
                        <li
                            v-for="(_html, index) in varDumpHtmls(slotProps.entry)"
                            :key="index"
                            class="nav-item"
                        >
                            <a
                                class="nav-link"
                                :class="{ active: currentTab === index }"
                                href="#"
                                @click.prevent="currentTab = index"
                            >{{ tabLabel(index, varDumpHtmls(slotProps.entry).length) }}</a>
                        </li>
                    </ul>
                    <collapsible-content
                        v-for="(html, index) in varDumpHtmls(slotProps.entry)"
                        :key="index"
                        v-show="currentTab === index"
                        :title="tabLabel(index, varDumpHtmls(slotProps.entry).length)"
                    >
                        <div
                            class="vardump-html"
                            v-html="html"
                        />
                    </collapsible-content>
                </div>
            </div>
        </template>
    </preview-screen>
</template>
