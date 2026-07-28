<template>
    <preview-screen
        :id="$route.params.id"
        title="Scheduled Command Details"
        resource="schedule"
    >
        <template #table-parameters="slotProps">
            <attribute-row
                title="Description"
                :value="slotProps.entry.content.description || '-'"
            />

            <attribute-row
                title="Command"
                code
                :value="slotProps.entry.content.command || '-'"
            />

            <attribute-row
                title="Expression"
                :value="slotProps.entry.content.expression"
            />

            <attribute-row
                title="User"
                :value="slotProps.entry.content.user || '-'"
            />

            <attribute-row
                title="Timezone"
                :value="slotProps.entry.content.timezone || '-'"
            />
        </template>

        <template #after-attributes-card="slotProps">
            <div v-if="slotProps.entry.content.output">
                <div class="card mt-5 overflow-hidden">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a class="nav-link active">Output</a>
                        </li>
                    </ul>

                    <copy-clipboard :data="slotProps.entry.content.output">
                        <pre class="code-bg p-4 mb-0 text-white">{{ slotProps.entry.content.output }}</pre>
                    </copy-clipboard>
                </div>
            </div>
        </template>
    </preview-screen>
</template>
