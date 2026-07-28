<script setup>
import { useHelpers } from '../../composables/useHelpers';

defineProps({
    items: { type: Array, default: () => [] },
});

const { truncate } = useHelpers();
</script>

<template>
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Email</th>
                <th />
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="item in items"
                :key="item.id"
            >
                <td>
                    <entry-title
                        :to="{ name: 'mail-preview', params: { id: item.id } }"
                        :text="item.content.mailable || '-'"
                        :limit="70"
                    >
                        <template #flags>
                            <flag-badge
                                :show="!!item.content.queued"
                                label="Queued"
                            />
                        </template>
                        <template #subtitle>
                            <span :title="item.content.subject">
                                Subject: {{ truncate(item.content.subject, 90) }}
                            </span>
                        </template>
                    </entry-title>
                </td>
                <view-link-cell :to="{ name: 'mail-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
