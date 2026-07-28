<script setup>
</script>

<template>
    <preview-screen
        :id="$route.params.id"
        title="Mongo Details"
        resource="mongo"
    >
        <template #table-parameters="slotProps">
            <attribute-row
                title="Command"
                :value="slotProps.entry.content.command"
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

            <attribute-row title="Duration">
                <span
                    v-if="slotProps.entry.content.slow || slotProps.entry.content.failed"
                    class="badge"
                    :class="slotProps.entry.content.failed ? 'badge-danger' : 'badge-warning'"
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
                        <a class="nav-link active">Payload</a>
                    </li>
                </ul>
                <div class="code-bg p-4 mb-0 text-white">
                    <copy-clipboard :data="slotProps.entry.content.payload || {}">
                        <vue-json-pretty :data="slotProps.entry.content.payload || {}" />
                    </copy-clipboard>
                </div>
            </div>
        </template>
    </preview-screen>
</template>
