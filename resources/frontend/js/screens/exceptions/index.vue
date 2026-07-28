<script setup>
import { useHelpers } from '@/composables/useHelpers';
import { useTimeAgo } from '@/composables/useTimeAgo';

const { truncate } = useHelpers();
const { timeAgo } = useTimeAgo();
</script>

<template>
    <index-screen
        title="Exceptions"
        resource="exceptions"
    >
        <template #table-header>
            <tr>
                <th
                    v-if="!$route.query.family_hash"
                    scope="col"
                >
                    Type
                </th>
                <th
                    v-if="!$route.query.family_hash && !$route.query.tag"
                    scope="col"
                    class="text-end"
                >
                    #
                </th>
                <th
                    v-if="$route.query.family_hash"
                    scope="col"
                >
                    Message
                </th>
                <th
                    scope="col"
                    class="text-end"
                >
                    Happened
                </th>
                <th scope="col">
                    Resolved
                </th>
                <th scope="col" />
            </tr>
        </template>

        <template #row="slotProps">
            <td v-if="!$route.query.family_hash">
                <entry-title
                    :to="{ name: 'exception-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.class"
                    :limit="70"
                    :subtitle="truncate(slotProps.entry.content.message, 100)"
                />
            </td>

            <td
                v-if="!$route.query.family_hash && !$route.query.tag"
                class="table-fit text-end text-muted"
            >
                <span>{{ slotProps.entry.content.occurrences }}</span>
            </td>

            <td v-if="$route.query.family_hash">
                <entry-title
                    :to="{ name: 'exception-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.message"
                    :limit="80"
                >
                    <template #subtitle>
                        <span v-if="slotProps.entry.content.user && slotProps.entry.content.user.email">
                            User: {{ slotProps.entry.content.user.email }} ({{ slotProps.entry.content.user.id }})
                        </span>

                        <span v-else> User: N/A </span>
                    </template>
                </entry-title>
            </td>

            <td
                class="table-fit text-end text-muted"
                :data-timeago="slotProps.entry.created"
                :title="slotProps.entry.created"
            >
                {{ timeAgo(slotProps.entry.created) }}
            </td>

            <td class="table-fit">
                <div
                    v-if="slotProps.entry.content.resolved_at"
                    :data-timeago="slotProps.entry.content.resolved_at"
                    :title="slotProps.entry.content.resolved_at"
                >
                    {{ timeAgo(slotProps.entry.content.resolved_at) }}
                </div>
                <div
                    v-if="!slotProps.entry.content.resolved_at"
                    class="control-action text-center"
                >
                    <svg
                        viewBox="0 0 20 20"
                        version="1.1"
                        xmlns="http://www.w3.org/2000/svg"
                        xmlns:xlink="http://www.w3.org/1999/xlink"
                    >
                        <path
                            id="Combined-Shape"
                            fill="#ef5753"
                            d="M2.92893219,17.0710678 C6.83417511,20.9763107 13.1658249,20.9763107 17.0710678,17.0710678 C20.9763107,13.1658249 20.9763107,6.83417511 17.0710678,2.92893219 C13.1658249,-0.976310729 6.83417511,-0.976310729 2.92893219,2.92893219 C-0.976310729,6.83417511 -0.976310729,13.1658249 2.92893219,17.0710678 Z M9,5 L11,5 L11,11 L9,11 L9,5 Z M9,13 L11,13 L11,15 L9,15 L9,13 Z"
                        />
                    </svg>
                </div>
            </td>

            <view-link-cell :to="{ name: 'exception-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
