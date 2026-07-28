<script setup>
import { computed } from 'vue';

function formatAddresses(addresses) {
    if (!addresses) {
        return '';
    }

    return Object.keys(addresses)
        .map((email) => {
            const name = addresses[email];

            return (name ? '<' + name + '> ' : '') + email;
        })
        .join(', ');
}

const mailApiBase = computed(() => {
    const base = window.Speculum?.basePath || '';

    return `${base}/api`;
});
</script>

<template>
    <preview-screen
        :id="$route.params.id"
        title="Mail Details"
        resource="mail"
    >
        <template #table-parameters="slotProps">
            <attribute-row title="Email">
                {{ slotProps.entry.content.mailable }}
                <flag-badge
                    :show="!!slotProps.entry.content.queued"
                    label="Queued"
                />
            </attribute-row>

            <attribute-row
                title="From"
                :value="formatAddresses(slotProps.entry.content.from)"
            />

            <attribute-row
                title="To"
                :value="formatAddresses(slotProps.entry.content.to)"
            />

            <attribute-row
                v-if="slotProps.entry.content.replyTo"
                title="Reply-To"
                :value="formatAddresses(slotProps.entry.content.replyTo)"
            />

            <attribute-row
                v-if="slotProps.entry.content.cc"
                title="CC"
                :value="formatAddresses(slotProps.entry.content.cc)"
            />

            <attribute-row
                v-if="slotProps.entry.content.bcc"
                title="BCC"
                :value="formatAddresses(slotProps.entry.content.bcc)"
            />

            <attribute-row
                title="Subject"
                :value="slotProps.entry.content.subject"
            />

            <attribute-row title="Download">
                <a :href="mailApiBase + '/mail/' + $route.params.id + '/download'">Download .eml file</a>
            </attribute-row>
        </template>

        <template #after-attributes-card>
            <div class="mt-5">
                <div class="card">
                    <iframe
                        class="mail-preview-frame"
                        :src="mailApiBase + '/mail/' + $route.params.id + '/preview'"
                        sandbox=""
                        referrerpolicy="no-referrer"
                        width="100%"
                        height="400"
                        title="Mail HTML preview"
                    />
                </div>
            </div>
        </template>
    </preview-screen>
</template>
