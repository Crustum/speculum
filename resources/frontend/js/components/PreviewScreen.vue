<script setup>
import { computed, getCurrentInstance, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../utils/api';
import { useAbortablePolling } from '../composables/useAbortablePolling';
import { useTimeAgo } from '../composables/useTimeAgo';

const props = defineProps({
    resource: { type: String, required: true },
    title: { type: String, required: true },
    id: { type: [String, Number], required: true },
    entryPoint: { type: Boolean, default: false },
});

const emit = defineEmits(['ready', 'update:entry', 'update:batch']);

const { localTime, timeAgo } = useTimeAgo();
const { abortRequests, resetRequests, signal, mayRetry } = useAbortablePolling();
const instance = getCurrentInstance();

const entry = ref(null);
const batch = ref(null);
const ready = ref(false);
let updateEntryTimeout = null;
const updateEntryTimer = 2500;

provide('previewEntry', entry);
provide('previewBatch', batch);

function syncParent(nextEntry, nextBatch) {
    let current = instance?.parent;

    while (current) {
        const parent = current.proxy;
        if (parent && ('entry' in parent || 'batch' in parent)) {
            if ('entry' in parent) {
                parent.entry = nextEntry;
            }

            if ('batch' in parent) {
                parent.batch = nextBatch;
            }

            return;
        }

        current = current.parent;
    }
}

const job = computed(() => (batch.value || []).find((item) => item.type === 'job'));
const request = computed(() => (batch.value || []).find((item) => item.type === 'request'));
const command = computed(() => (batch.value || []).find((item) => item.type === 'command'));

function loadEntry(after) {
    const activeSignal = signal();

    return api
        .get(`/${props.resource}/${props.id}`, { signal: activeSignal })
        .then((response) => {
            if (activeSignal.aborted) {
                return;
            }

            if (typeof after === 'function') {
                after(response);
            }
        })
        .catch((error) => {
            if (activeSignal.aborted) {
                return;
            }

            ready.value = true;

            if (mayRetry(error, activeSignal)) {
                updateEntry();
            }
        });
}

function updateEntry() {
    if (props.resource !== 'jobs') {
        return;
    }

    if (!entry.value || entry.value.content?.status !== 'pending') {
        return;
    }

    updateEntryTimeout = setTimeout(() => {
        loadEntry((response) => {
            entry.value = response.data.entry;
            batch.value = response.data.batch;
            syncParent(response.data.entry, response.data.batch);
            emit('update:entry', response.data.entry);
            emit('update:batch', response.data.batch);
            ready.value = true;

            updateEntry();
        });
    }, updateEntryTimer);
}

function prepareEntry() {
    document.title = props.title + ' - Speculum';
    ready.value = false;

    loadEntry((response) => {
        entry.value = response.data.entry;
        batch.value = response.data.batch;
        syncParent(response.data.entry, response.data.batch);
        emit('update:entry', response.data.entry);
        emit('update:batch', response.data.batch);
        emit('ready');
        ready.value = true;
        updateEntry();
    });
}

watch(
    () => props.id,
    () => {
        resetRequests();
        clearTimeout(updateEntryTimeout);
        prepareEntry();
    }
);

onMounted(prepareEntry);

onBeforeUnmount(() => {
    abortRequests();
    clearTimeout(updateEntryTimeout);
});
</script>

<template>
    <div>
        <div class="card overflow-hidden">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h2 class="h6 m-0">
                    {{ title }}
                </h2>
            </div>

            <div
                v-if="!ready"
                class="d-flex align-items-center justify-content-center card-bg-secondary p-5 bottom-radius"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    class="icon spin me-2"
                >
                    <path
                        d="M12 10a2 2 0 0 1-3.41 1.41A2 2 0 0 1 10 8V0a9.97 9.97 0 0 1 10 10h-8zm7.9 1.41A10 10 0 1 1 8.59.1v2.03a8 8 0 1 0 9.29 9.29h2.02zm-4.07 0a6 6 0 1 1-7.25-7.25v2.1a3.99 3.99 0 0 0-1.4 6.57 4 4 0 0 0 6.56-1.42h2.1z"
                    />
                </svg>
                <span>Fetching...</span>
            </div>

            <div
                v-if="ready && !entry"
                class="d-flex align-items-center justify-content-center card-bg-secondary p-5 bottom-radius"
            >
                <span>No entry found.</span>
            </div>

            <div class="table-responsive border-top">
                <table
                    v-if="ready && entry"
                    class="table mb-0 card-bg-secondary table-borderless"
                >
                    <tbody>
                        <attribute-row title="Time">
                            {{ localTime(entry.created) }} ({{ timeAgo(entry.created) }})
                        </attribute-row>

                        <attribute-row
                            title="Hostname"
                            :value="entry.content.hostname"
                        />

                        <slot
                            name="table-parameters"
                            :entry="entry"
                        />

                        <attribute-row
                            v-if="!entryPoint && job"
                            title="Job"
                        >
                            <RouterLink
                                :to="{ name: 'job-preview', params: { id: job.id } }"
                                class="control-action"
                            >
                                View Job
                            </RouterLink>
                        </attribute-row>

                        <attribute-row
                            v-if="!entryPoint && request"
                            title="Request"
                        >
                            <RouterLink
                                :to="{ name: 'request-preview', params: { id: request.id } }"
                                class="control-action"
                            >
                                View Request
                            </RouterLink>
                        </attribute-row>

                        <attribute-row
                            v-if="!entryPoint && command"
                            title="Command"
                        >
                            <RouterLink
                                :to="{ name: 'command-preview', params: { id: command.id } }"
                                class="control-action"
                            >
                                View Command
                            </RouterLink>
                        </attribute-row>

                        <attribute-row
                            v-if="entry.tags && entry.tags.length"
                            title="Tags"
                        >
                            <RouterLink
                                v-for="tag in entry.tags"
                                :key="tag"
                                :to="{ name: resource, query: { tag: tag } }"
                                class="badge badge-info me-1"
                            >
                                {{ tag }}
                            </RouterLink>
                        </attribute-row>
                    </tbody>
                </table>
            </div>

            <slot
                v-if="ready && entry"
                name="below-table"
                :entry="entry"
                :batch="batch || []"
            />
        </div>

        <div
            v-if="ready && entry && entry.content.user && entry.content.user.id"
            class="card mt-5"
        >
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5>Authenticated User</h5>
            </div>

            <table class="table mb-0 card-bg-secondary table-borderless">
                <tbody>
                    <attribute-row
                        title="ID"
                        :value="entry.content.user.id"
                    />

                    <tr v-if="entry.content.user.name">
                        <td class="table-fit text-muted align-middle">
                            Name
                        </td>
                        <td class="align-middle">
                            <img
                                v-if="entry.content.user.avatar"
                                :src="entry.content.user.avatar"
                                :alt="entry.content.user.name"
                                class="me-2 rounded-circle"
                                height="40"
                                width="40"
                            >
                            {{ entry.content.user.name }}
                        </td>
                    </tr>

                    <attribute-row
                        v-if="entry.content.user.email"
                        title="Email Address"
                        :value="entry.content.user.email"
                    />
                </tbody>
            </table>
        </div>

        <slot
            v-if="ready && entry"
            name="after-attributes-card"
            :entry="entry"
            :batch="batch || []"
        />

        <related-entries
            v-if="ready && entry"
            :entry="entry"
            :batch="batch || []"
        />
    </div>
</template>
