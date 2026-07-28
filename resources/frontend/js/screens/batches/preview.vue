<script setup>
import { useTimeAgo } from '@/composables/useTimeAgo';

const { localTime, timeAgo } = useTimeAgo();
</script>

<template>
    <preview-screen
        :id="$route.params.id"
        title="Batch Details"
        resource="batches"
        entry-point="true"
    >
        <template #table-parameters="slotProps">
            <attribute-row title="Status">
                <small
                    v-if="slotProps.entry.content.failedJobs > 0 && slotProps.entry.content.progress < 100"
                    class="badge badge-danger badge-sm"
                >
                    Failures
                </small>
                <small
                    v-if="slotProps.entry.content.progress == 100"
                    class="badge badge-success badge-sm"
                >
                    Finished
                </small>
                <small
                    v-if="
                        slotProps.entry.content.totalJobs == 0 ||
                            (slotProps.entry.content.pendingJobs > 0 && !slotProps.entry.content.failedJobs)
                    "
                    class="badge badge-secondary badge-sm"
                >
                    Pending
                </small>
            </attribute-row>

            <attribute-row
                v-if="slotProps.entry.content.cancelledAt"
                title="Cancelled At"
            >
                {{ localTime(slotProps.entry.content.cancelledAt) }} ({{
                    timeAgo(slotProps.entry.content.cancelledAt)
                }})
            </attribute-row>

            <attribute-row
                v-if="slotProps.entry.content.finished"
                title="Finished At"
            >
                {{ localTime(slotProps.entry.content.finished) }} ({{
                    timeAgo(slotProps.entry.content.finished)
                }})
            </attribute-row>

            <attribute-row
                title="Batch"
                :value="slotProps.entry.content.name || slotProps.entry.content.id"
            />

            <attribute-row
                title="Connection"
                :value="slotProps.entry.content.connection"
            />

            <attribute-row
                title="Queue"
                :value="slotProps.entry.content.queue"
            />

            <attribute-row title="Size">
                <router-link
                    :to="{
                        name: 'jobs',
                        query: { family_hash: slotProps.entry.family_hash },
                    }"
                    class="control-action"
                >
                    {{ slotProps.entry.content.totalJobs }} Jobs
                </router-link>
            </attribute-row>

            <attribute-row
                title="Pending"
                :value="slotProps.entry.content.pendingJobs"
            />

            <attribute-row title="Progress">
                {{ slotProps.entry.content.progress }}%
            </attribute-row>
        </template>
    </preview-screen>
</template>
