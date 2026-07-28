<script setup>
import { useHelpers } from '@/composables/useHelpers';

const { truncate } = useHelpers();

function recipientsCount(entry) {
    const keys = [
        ...(entry.content.to ? Object.keys(entry.content.to) : []),
        ...(entry.content.cc ? Object.keys(entry.content.cc) : []),
        ...(entry.content.bcc ? Object.keys(entry.content.bcc) : []),
        ...(entry.content.replyTo ? Object.keys(entry.content.replyTo) : []),
    ];

    return [...new Set(keys)].length;
}
</script>

<template>
    <index-screen
        title="Mail"
        resource="mail"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Email
                </th>
                <th
                    scope="col"
                    class="text-end"
                >
                    Recipients
                </th>
                <th scope="col">
                    Happened
                </th>
                <th scope="col" />
            </tr>
        </template>

        <template #row="slotProps">
            <td>
                <entry-title
                    :to="{ name: 'mail-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.mailable || '-'"
                    :limit="70"
                >
                    <template #flags>
                        <flag-badge
                            :show="!!slotProps.entry.content.queued"
                            label="Queued"
                        />
                    </template>
                    <template #subtitle>
                        <span :title="slotProps.entry.content.subject">
                            Subject: {{ truncate(slotProps.entry.content.subject, 90) }}
                        </span>
                    </template>
                </entry-title>
            </td>

            <td class="table-fit text-end text-muted">
                {{ recipientsCount(slotProps.entry) }}
            </td>

            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'mail-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
