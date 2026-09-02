<script setup>
import { formatFileLocation, editorHref } from '../../utils/projectPath';

function parseQueryPayload(query) {
    if (query == null || query === '') {
        return {};
    }

    if (typeof query === 'object') {
        return query;
    }

    try {
        const parsed = JSON.parse(query);

        return parsed && typeof parsed === 'object' ? parsed : { raw: query };
    } catch (_e) {
        return { raw: query };
    }
}
</script>

<template>
    <preview-screen
        :id="$route.params.id"
        title="Mongo Query Details"
        resource="mongo-queries"
    >
        <template #table-parameters="slotProps">
            <attribute-row
                title="Connection"
                :value="slotProps.entry.content.connection"
            />

            <attribute-row
                v-if="slotProps.entry.content.operation"
                title="Operation"
                :value="slotProps.entry.content.operation"
                code
            />

            <attribute-row
                v-if="slotProps.entry.content.database"
                title="Database"
                :value="slotProps.entry.content.database"
            />

            <attribute-row
                v-if="slotProps.entry.content.collection"
                title="Collection"
                :value="slotProps.entry.content.collection"
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
                <span v-else>{{ slotProps.entry.content.time }}ms</span>
            </attribute-row>
        </template>

        <template #after-attributes-card="slotProps">
            <div class="card mt-5 overflow-hidden">
                <ul class="nav nav-pills">
                    <li class="nav-item">
                        <a class="nav-link active">Query</a>
                    </li>
                </ul>
                <div class="code-bg p-4 mb-0 text-white">
                    <copy-clipboard :data="parseQueryPayload(slotProps.entry.content.query)">
                        <vue-json-pretty :data="parseQueryPayload(slotProps.entry.content.query)" />
                    </copy-clipboard>
                </div>
            </div>
        </template>
    </preview-screen>
</template>
