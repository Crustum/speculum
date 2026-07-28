<template>
    <preview-screen
        :id="$route.params.id"
        title="Event Details"
        resource="events"
    >
        <template #table-parameters="slotProps">
            <attribute-row title="Event">
                {{ slotProps.entry.content.name }}
                <flag-badge
                    :show="!!slotProps.entry.content.broadcast"
                    label="Broadcast"
                />
            </attribute-row>
        </template>

        <template #after-attributes-card="slotProps">
            <div>
                <div class="card mt-5 overflow-hidden">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a class="nav-link active">Event Data</a>
                        </li>
                    </ul>
                    <div class="code-bg p-4 mb-0 text-white">
                        <copy-clipboard :data="slotProps.entry.content.payload">
                            <vue-json-pretty :data="slotProps.entry.content.payload" />
                        </copy-clipboard>
                    </div>
                </div>
            </div>
        </template>
    </preview-screen>
</template>
