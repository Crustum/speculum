<script setup>
import { computed, inject, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { useRoute, useRouter } from 'vue-router';
import api from '../utils/api';
import { useHelpers } from '../composables/useHelpers';
import { useTimeAgo } from '../composables/useTimeAgo';

const props = defineProps({
    resource: { type: String, required: true },
    title: { type: String, required: true },
    showAllFamily: { type: Boolean, default: false },
    hideSearch: { type: Boolean, default: false },
    slowFilterLabel: { type: String, default: '' },
});

const route = useRoute();
const router = useRouter();
const { debouncer } = useHelpers();
const { timeAgo } = useTimeAgo();
const autoLoadsNewEntries = inject('autoLoadsNewEntries', ref(false));

const tag = ref('');
const familyHash = ref('');
const orderBy = ref('sequence');
const entries = ref([]);
const ready = ref(false);
const loadError = ref(false);
const recordingStatus = ref('enabled');
const lastEntryIndex = ref('');
const lastEntryDuration = ref(null);
const hasMoreEntries = ref(true);
const hasNewEntries = ref(false);
const entriesPerRequest = 50;
const loadingNewEntries = ref(false);
const loadingMoreEntries = ref(false);
const destroyed = ref(false);

let newEntriesTimeout = null;
let updateEntriesTimeout = null;
let updateTimeAgoTimeout = null;
const newEntriesTimer = 2500;
const updateEntriesTimer = 2500;
const timeAgoTick = ref(0);

function uniqueByFamily(items) {
    const seen = new Set();
    const result = [];

    items.forEach((entry) => {
        const key = entry.family_hash || entry.id;

        if (!seen.has(key)) {
            seen.add(key);
            result.push(entry);
        }
    });

    return result;
}

function entriesQuery(params = {}) {
    const query = new URLSearchParams({
        tag: tag.value,
        take: String(params.take ?? entriesPerRequest),
        family_hash: familyHash.value,
    });

    if (params.before !== undefined && params.before !== '') {
        query.set('before', String(params.before));
    }

    if (orderBy.value === 'duration') {
        query.set('order_by', 'duration');
        query.set('order_direction', 'desc');
        if (params.beforeDuration != null && params.beforeDuration !== '') {
            query.set('before_duration', String(params.beforeDuration));
        }
    }

    return query.toString();
}

function loadEntries(after) {
    return api
        .post(
            `/${props.resource}?${entriesQuery({
                before: lastEntryIndex.value,
                beforeDuration: lastEntryDuration.value,
            })}`
        )
        .then((response) => {
            loadError.value = false;
            const list = response.data.entries || [];

            if (list.length) {
                const last = list[list.length - 1];
                lastEntryIndex.value = last.sequence;
                lastEntryDuration.value = last.duration ?? null;
            }

            hasMoreEntries.value = list.length >= entriesPerRequest;
            recordingStatus.value = response.data.status;

            if (typeof after === 'function') {
                after(familyHash.value || props.showAllFamily ? list : uniqueByFamily(list));
            }
        })
        .catch(() => {
            loadError.value = true;
            hasMoreEntries.value = false;
            loadingNewEntries.value = false;
            loadingMoreEntries.value = false;

            if (typeof after === 'function') {
                after([]);
            }
        });
}

function checkForNewEntries() {
    if (orderBy.value === 'duration') {
        return;
    }

    newEntriesTimeout = setTimeout(() => {
        api
            .post(
                `/${props.resource}?${entriesQuery({ take: 1 })}`
            )
            .then((response) => {
                if (destroyed.value) {
                    return;
                }

                recordingStatus.value = response.data.status;
                const list = response.data.entries || [];

                if (list.length && !entries.value.length) {
                    loadNewEntries();
                } else if (list.length && entries.value.length && list[0].id !== entries.value[0].id) {
                    if (autoLoadsNewEntries.value) {
                        loadNewEntries();
                    } else {
                        hasNewEntries.value = true;
                    }
                } else {
                    checkForNewEntries();
                }
            })
            .catch(() => {
                if (!destroyed.value) {
                    checkForNewEntries();
                }
            });
    }, newEntriesTimer);
}

function updateTimeAgo() {
    updateTimeAgoTimeout = setTimeout(() => {
        timeAgoTick.value += 1;
        updateTimeAgo();
    }, 60000);
}

const isSlowFilterActive = computed(() => {
    return tag.value
        .split(',')
        .map((part) => part.trim())
        .includes('slow');
});

const isDurationSortActive = computed(() => orderBy.value === 'duration');

const showIndexToolbar = computed(() => {
    return !props.hideSearch && (Boolean(props.slowFilterLabel) || Boolean(tag.value) || entries.value.length > 0);
});

function resetListCursor() {
    hasNewEntries.value = false;
    lastEntryIndex.value = '';
    lastEntryDuration.value = null;
    clearTimeout(newEntriesTimeout);
}

function applyTagFilter(nextTag) {
    tag.value = nextTag;
    resetListCursor();

    const query = { ...route.query };

    if (nextTag) {
        query.tag = nextTag;
    } else {
        delete query.tag;
    }

    router.push({ query });
}

function applyDurationSort(enabled) {
    orderBy.value = enabled ? 'duration' : 'sequence';
    resetListCursor();

    const query = { ...route.query };

    if (enabled) {
        query.order_by = 'duration';
    } else {
        delete query.order_by;
    }

    router.push({ query });
}

function search() {
    debouncer(() => {
        applyTagFilter(tag.value);
    });
}

function toggleSlowFilter() {
    applyTagFilter(isSlowFilterActive.value ? '' : 'slow');
}

function toggleDurationSort() {
    applyDurationSort(!isDurationSortActive.value);
}

function loadOlderEntries() {
    loadingMoreEntries.value = true;

    loadEntries((list) => {
        entries.value.push(...list);
        loadingMoreEntries.value = false;
    });
}

function loadNewEntries() {
    hasMoreEntries.value = true;
    resetListCursor();
    loadingNewEntries.value = true;

    loadEntries((list) => {
        entries.value = list;
        loadingNewEntries.value = false;
        checkForNewEntries();
    });
}

function updateEntries() {
    if (props.resource !== 'jobs') {
        return;
    }

    updateEntriesTimeout = setTimeout(() => {
        const uuids = entries.value
            .filter((entry) => entry.content?.status === 'pending')
            .map((entry) => entry.id);


        if (uuids.length) {
            api.post(`/${props.resource}`, { uuids })
                .then((response) => {
                    recordingStatus.value = response.data.status;
                    const refreshed = response.data.entries || [];

                    entries.value = entries.value.map((entry) => {
                        if (!uuids.includes(entry.id)) {
                            return entry;
                        }

                        return refreshed.find((item) => item.id === entry.id) || entry;
                    });
                })
                .catch(() => {});
        }

        updateEntries();
    }, updateEntriesTimer);
}

function focusOnSearch() {
    document.onkeyup = (event) => {
        if (event.which === 191 || event.keyCode === 191) {
            const searchInput = document.getElementById('searchInput');

            if (searchInput) {
                searchInput.focus();
            }
        }
    };
}

function bootstrap() {
    document.title = props.title + ' - Speculum';
    familyHash.value = route.query.family_hash || '';
    tag.value = route.query.tag || '';
    orderBy.value = route.query.order_by === 'duration' ? 'duration' : 'sequence';

    loadEntries((list) => {
        entries.value = list;
        checkForNewEntries();
        ready.value = true;
    });

    updateEntries();
    updateTimeAgo();
    focusOnSearch();
}

watch(
    () => route.query,
    () => {
        clearTimeout(newEntriesTimeout);
        hasNewEntries.value = false;
        lastEntryIndex.value = '';
        lastEntryDuration.value = null;

        if (!route.query.family_hash) {
            familyHash.value = '';
        }

        if (!route.query.tag) {
            tag.value = '';
        } else {
            tag.value = String(route.query.tag);
        }

        orderBy.value = route.query.order_by === 'duration' ? 'duration' : 'sequence';

        ready.value = false;
        loadError.value = false;

        loadEntries((list) => {
            entries.value = list;
            checkForNewEntries();
            ready.value = true;
        });
    }
);

onMounted(bootstrap);

onBeforeUnmount(() => {
    destroyed.value = true;
    clearTimeout(newEntriesTimeout);
    clearTimeout(updateEntriesTimeout);
    clearTimeout(updateTimeAgoTimeout);
    document.onkeyup = null;
});

defineExpose({ timeAgo, timeAgoTick });
</script>

<template>
    <div class="card overflow-hidden">
        <div class="card-header d-flex align-items-center gap-2">
            <h2 class="h6 m-0 me-auto">
                {{ title }}
            </h2>

            <button
                v-if="showIndexToolbar && slowFilterLabel"
                type="button"
                class="btn btn-muted d-flex align-items-center py-2 flex-shrink-0"
                :class="{ active: isSlowFilterActive }"
                :title="slowFilterLabel"
                @click="toggleSlowFilter"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    class="icon"
                    fill="currentColor"
                >
                    <path
                        fill-rule="evenodd"
                        d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                        clip-rule="evenodd"
                    />
                </svg>
            </button>
            <button
                v-if="showIndexToolbar && slowFilterLabel"
                type="button"
                class="btn btn-muted d-flex align-items-center py-2 flex-shrink-0"
                :class="{ active: isDurationSortActive }"
                title="Sort by duration"
                @click="toggleDurationSort"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    class="icon"
                    fill="currentColor"
                >
                    <path
                        fill-rule="evenodd"
                        d="M2.24 6.8a.75.75 0 001.06-.04l1.95-2.1v8.59a.75.75 0 001.5 0V4.66l1.95 2.1a.75.75 0 101.1-1.02l-3.25-3.5a.75.75 0 00-1.1 0L2.2 5.74a.75.75 0 00.04 1.06zm8 6.4a.75.75 0 00-.04 1.06l3.25 3.5a.75.75 0 001.1 0l3.25-3.5a.75.75 0 00-1.1-1.02l-1.95 2.1V6.75a.75.75 0 00-1.5 0v8.59l-1.95-2.1a.75.75 0 00-1.06-.04z"
                        clip-rule="evenodd"
                    />
                </svg>
            </button>
            <div
                v-if="showIndexToolbar"
                class="form-control-with-icon w-25"
            >
                <div class="icon-wrapper">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        class="icon"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </div>
                <input
                    id="searchInput"
                    v-model="tag"
                    type="text"
                    class="form-control w-100"
                    placeholder="Search Tag"
                    @input.stop="search"
                >
            </div>
        </div>

        <p
            v-if="recordingStatus !== 'enabled'"
            class="mt-0 mb-0 disabled-watcher d-flex align-items-center"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                x="0px"
                y="0px"
                width="20px"
                height="20px"
                viewBox="0 0 90 90"
                class="me-2"
            >
                <path
                    fill="#FFFFFF"
                    d="M45 0C20.1 0 0 20.1 0 45s20.1 45 45 45 45-20.1 45-45S69.9 0 45 0zM45 74.5c-3.6 0-6.5-2.9-6.5-6.5s2.9-6.5 6.5-6.5 6.5 2.9 6.5 6.5S48.6 74.5 45 74.5zM52.1 23.9l-2.5 29.6c0 2.5-2.1 4.6-4.6 4.6 -2.5 0-4.6-2.1-4.6-4.6l-2.5-29.6c-0.1-0.4-0.1-0.7-0.1-1.1 0-4 3.2-7.2 7.2-7.2 4 0 7.2 3.2 7.2 7.2C52.2 23.1 52.2 23.5 52.1 23.9z"
                />
            </svg>
            <span
                v-if="recordingStatus == 'disabled'"
                class="ms-1"
            >Speculum is currently disabled.</span>
            <span
                v-if="recordingStatus == 'paused'"
                class="ms-1"
            >Speculum recording is paused.</span>
            <span
                v-if="recordingStatus == 'off'"
                class="ms-1"
            >This watcher is turned off.</span>
        </p>

        <div
            v-if="!ready"
            class="d-flex align-items-center justify-content-center card-bg-secondary p-5 bottom-radius"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                class="icon spin me-2 fill-text-color"
            >
                <path
                    d="M12 10a2 2 0 0 1-3.41 1.41A2 2 0 0 1 10 8V0a9.97 9.97 0 0 1 10 10h-8zm7.9 1.41A10 10 0 1 1 8.59.1v2.03a8 8 0 1 0 9.29 9.29h2.02zm-4.07 0a6 6 0 1 1-7.25-7.25v2.1a3.99 3.99 0 0 0-1.4 6.57 4 4 0 0 0 6.56-1.42h2.1z"
                />
            </svg>
            <span>Scanning...</span>
        </div>

        <div
            v-if="ready && loadError"
            class="d-flex flex-column align-items-center justify-content-center card-bg-secondary p-5 bottom-radius"
        >
            <span>Failed to load entries.</span>
        </div>

        <div
            v-if="ready && !loadError && entries.length == 0"
            class="d-flex flex-column align-items-center justify-content-center card-bg-secondary p-5 bottom-radius"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 80 80"
                class="fill-text-color"
                style="width: 120px"
            >
                <path
                    fill-rule="evenodd"
                    d="M40 6C23.431 6 10 19.431 10 36c0 14.012 9.58 25.74 22.5 29.05V72h-5.5a3.5 3.5 0 100 7h26a3.5 3.5 0 100-7H47.5v-6.95C60.42 61.74 70 50.012 70 36 70 19.431 56.569 6 40 6zm0 10c11.046 0 20 8.954 20 20s-8.954 20-20 20-20-8.954-20-20 8.954-20 20-20zm-8 8a2.5 2.5 0 012.5-2.5h7a2.5 2.5 0 010 5h-7A2.5 2.5 0 0132 24z"
                />
            </svg>
            <span class="mt-5 pt-2">We didn't find anything - just empty space.</span>
        </div>

        <table
            v-if="ready && !loadError && entries.length > 0"
            id="indexScreen"
            class="table table-hover mb-0 penultimate-column-right"
        >
            <thead>
                <slot name="table-header" />
            </thead>

            <transition-group
                tag="tbody"
                name="list"
            >
                <tr
                    v-if="hasNewEntries"
                    key="newEntries"
                    class="dontanimate"
                >
                    <td
                        colspan="100"
                        class="text-center card-bg-secondary py-2"
                    >
                        <small>
                            <a
                                v-if="!loadingNewEntries"
                                href="#"
                                @click.prevent="loadNewEntries"
                            >Load New Entries</a>
                        </small>
                        <small v-if="loadingNewEntries">Loading...</small>
                    </td>
                </tr>

                <tr
                    v-for="entry in entries"
                    :key="entry.id"
                >
                    <slot
                        name="row"
                        :entry="entry"
                        :time-ago-tick="timeAgoTick"
                    />
                </tr>

                <tr
                    v-if="hasMoreEntries"
                    key="olderEntries"
                    class="dontanimate"
                >
                    <td
                        colspan="100"
                        class="text-center card-bg-secondary py-2"
                    >
                        <small>
                            <a
                                v-if="!loadingMoreEntries"
                                href="#"
                                @click.prevent="loadOlderEntries"
                            >Load Older Entries</a>
                        </small>
                        <small v-if="loadingMoreEntries">Loading...</small>
                    </td>
                </tr>
            </transition-group>
        </table>
    </div>
</template>
