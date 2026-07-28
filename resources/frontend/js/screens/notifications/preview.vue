<template>
    <preview-screen
        :id="$route.params.id"
        title="Notification Details"
        resource="notifications"
    >
        <template #table-parameters="slotProps">
            <attribute-row
                title="Channel"
                :value="slotProps.entry.content.channel"
            />

            <attribute-row title="Notification">
                {{ slotProps.entry.content.notification }}
                <flag-badge
                    :show="!!slotProps.entry.content.queued"
                    label="Queued"
                />
            </attribute-row>

            <attribute-row
                title="Notifiable"
                :value="slotProps.entry.content.notifiable"
            />
        </template>
    </preview-screen>
</template>
