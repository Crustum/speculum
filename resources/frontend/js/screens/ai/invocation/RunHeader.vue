<script setup>
import { formatDateTime } from '@/utils/aiTool';

defineProps({
    invocationId: { type: String, required: true },
    summary: { type: Object, required: true },
    countLine: { type: String, required: true },
});
</script>

<template>
    <div class="card overflow-hidden mb-4">
        <div class="card-header d-flex align-items-center gap-2">
            <h5 class="mb-0 me-auto">
                Run
            </h5>
            <flag-badge
                :show="summary.failed"
                label="Failed"
                variant="danger"
            />
        </div>
        <table class="table mb-0 card-bg-secondary table-borderless">
            <tbody>
                <attribute-row title="Invocation ID">
                    <span class="text-monospace">{{ invocationId }}</span>
                </attribute-row>
                <attribute-row
                    v-if="summary.model"
                    title="Model"
                    :value="summary.model"
                />
                <attribute-row
                    v-if="summary.provider"
                    title="Provider"
                    :value="summary.provider"
                />
                <attribute-row title="Events">
                    {{ summary.total }} <span class="text-muted">({{ countLine }})</span>
                </attribute-row>
                <attribute-row
                    v-if="summary.started"
                    title="Started"
                >
                    {{ formatDateTime(summary.started) }}
                </attribute-row>
                <attribute-row
                    v-if="summary.finished"
                    title="Finished"
                >
                    {{ formatDateTime(summary.finished) }}
                </attribute-row>
            </tbody>
        </table>
    </div>
</template>
