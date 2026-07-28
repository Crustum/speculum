<script setup>
function correlationId(content) {
    if (!content || typeof content.correlation_id !== 'string' || content.correlation_id === '') {
        return null;
    }

    return content.correlation_id;
}
</script>

<template>
    <preview-screen
        :id="$route.params.id"
        title="Broadcast Details"
        resource="broadcasts"
    >
        <template #table-parameters="slotProps">
            <attribute-row title="Event">
                {{ slotProps.entry.content.event }}
                <flag-badge
                    :show="!!slotProps.entry.content.queued"
                    label="Queued"
                />
            </attribute-row>

            <attribute-row
                title="Channels"
                :value="(slotProps.entry.content.channels || []).join(', ')"
            />

            <attribute-row
                title="Connection"
                :value="slotProps.entry.content.connection"
            />

            <attribute-row
                v-if="correlationId(slotProps.entry.content)"
                title="Correlation"
            >
                <code>{{ correlationId(slotProps.entry.content) }}</code>
                <router-link
                    class="ms-2"
                    :to="{
                        path: '/blazecast',
                        query: { tag: 'correlation:' + correlationId(slotProps.entry.content) },
                    }"
                >
                    View BlazeCast
                </router-link>
            </attribute-row>
        </template>

        <template #after-attributes-card="slotProps">
            <div
                v-if="slotProps.entry.content.payload !== undefined && slotProps.entry.content.payload !== null"
                class="card mt-5 overflow-hidden"
            >
                <ul class="nav nav-pills">
                    <li class="nav-item">
                        <a class="nav-link active">Payload</a>
                    </li>
                </ul>
                <div class="code-bg p-4 mb-0 text-white">
                    <copy-clipboard :data="slotProps.entry.content.payload">
                        <vue-json-pretty :data="slotProps.entry.content.payload" />
                    </copy-clipboard>
                </div>
            </div>
        </template>
    </preview-screen>
</template>
