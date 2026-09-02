<script setup>
</script>

<template>
    <index-screen
        title="Authorization"
        resource="authorization"
    >
        <template #table-header>
            <tr>
                <th scope="col">
                    Ability
                </th>
                <th scope="col">
                    Result
                </th>
                <th scope="col">
                    Role
                </th>
                <th scope="col">
                    Policy
                </th>
                <th scope="col">
                    Happened
                </th>
                <th scope="col" />
            </tr>
        </template>

        <template #row="slotProps">
            <td>
                <entry-link
                    :to="{ name: 'authorization-preview', params: { id: slotProps.entry.id } }"
                    :text="slotProps.entry.content.ability"
                    :limit="85"
                    code
                />
            </td>

            <td>
                <span
                    class="badge"
                    :class="slotProps.entry.content.allowed ? 'badge-success' : 'badge-danger'"
                >
                    {{ slotProps.entry.content.allowed ? 'Allowed' : 'Denied' }}
                </span>
            </td>

            <muted-text-cell
                fit
                :text="slotProps.entry.content.role || '-'"
                :limit="60"
            />
            <muted-text-cell
                fit
                :text="slotProps.entry.content.policy || '-'"
                :limit="60"
                code
            />
            <time-ago-cell :value="slotProps.entry.created" />
            <view-link-cell :to="{ name: 'authorization-preview', params: { id: slotProps.entry.id } }" />
        </template>
    </index-screen>
</template>
