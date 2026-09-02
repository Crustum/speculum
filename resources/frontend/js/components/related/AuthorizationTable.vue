<script setup>
import { useEntryStyles } from '@/composables/useEntryStyles';

const { gateResultClass } = useEntryStyles();

const { items } = defineProps({
    items: { type: Array, default: () => [] },
});
</script>

<template>
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>
                    Ability<br>
                    <small>{{ items.length }} entries</small>
                </th>
                <th>Role</th>
                <th>Result</th>
                <th />
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="item in items"
                :key="item.id"
            >
                <td :title="item.content.ability">
                    <entry-link
                        :to="{ name: 'authorization-preview', params: { id: item.id } }"
                        :text="item.content.ability || '-'"
                        :limit="70"
                    />
                    <span
                        v-if="item.content.link_check"
                        class="badge badge-secondary ms-2"
                    >Link</span>
                </td>
                <td class="table-fit text-muted">
                    {{ item.content.role || '-' }}
                </td>
                <td class="table-fit">
                    <span
                        class="badge"
                        :class="'badge-' + gateResultClass(item.content.allowed ? 'allowed' : 'denied')"
                    >
                        {{ item.content.allowed ? 'Allowed' : 'Denied' }}
                    </span>
                </td>
                <view-link-cell :to="{ name: 'authorization-preview', params: { id: item.id } }" />
            </tr>
        </tbody>
    </table>
</template>
