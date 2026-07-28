<template>
    <preview-screen
        :id="$route.params.id"
        title="BlazeCast"
        resource="blazecast"
    >
        <template #table-parameters="slotProps">
            <attribute-row
                title="Direction"
                :value="
                    slotProps.entry.content.direction === 'in' || slotProps.entry.type === 'bc_message'
                        ? 'Incoming'
                        : 'Outgoing'
                "
            />
            <attribute-row
                title="Event"
                :value="slotProps.entry.content.event"
            />
            <attribute-row
                title="Channel"
                :value="slotProps.entry.content.channel || '-'"
            />
            <attribute-row
                v-if="slotProps.entry.content.app_id"
                title="App"
                :value="slotProps.entry.content.app_id"
            />
            <attribute-row
                v-if="slotProps.entry.content.connection_id"
                title="Connection"
                :value="slotProps.entry.content.connection_id"
            />
            <attribute-row
                v-if="slotProps.entry.content.delivered_to !== undefined"
                title="Delivered to"
                :value="slotProps.entry.content.delivered_to ?? 0"
            />
            <attribute-row
                v-if="(slotProps.entry.content.connection_ids || []).length"
                title="Connections"
            >
                <code
                    v-for="(id, index) in slotProps.entry.content.connection_ids"
                    :key="index"
                    class="me-2"
                >{{ id }}</code>
            </attribute-row>
            <attribute-row
                v-if="slotProps.entry.content.correlation_id"
                title="Correlation"
            >
                <code>{{ slotProps.entry.content.correlation_id }}</code>
                <router-link
                    class="ms-2"
                    :to="{ path: '/broadcasts', query: { tag: 'correlation:' + slotProps.entry.content.correlation_id } }"
                >
                    Find broadcast
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
