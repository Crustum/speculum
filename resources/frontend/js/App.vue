<script setup>
import { computed, onBeforeUnmount, onMounted, provide, reactive, ref } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import api from './utils/api';
import Alert from './components/Alert.vue';
import SchemeToggler from './components/SchemeToggler.vue';
import SetupModal from './components/SetupModal.vue';
import { ALERT_KEY } from './composables/useAlert';
import { localRoot } from './utils/projectPath';
import { getExtensionNavItems } from './extensions/registry';

const LOCALSTORAGE_AUTOLOAD_KEY = 'speculumAutoLoadsNewEntries';

const props = defineProps({
    availableWatchers: {
        type: Array,
        default: null,
    },
});

const router = useRouter();

const alert = reactive({
    type: null,
    autoClose: 0,
    message: '',
    confirmationProceed: null,
    confirmationCancel: null,
});

const autoLoadsNewEntries = ref(localStorage[LOCALSTORAGE_AUTOLOAD_KEY] === '1');
const recording = ref(window.Speculum?.recording ?? true);
const setupOpen = ref(false);
const hasLocalRoot = ref(Boolean(localRoot()));
const metaWatchers = ref(
    Array.isArray(props.availableWatchers)
        ? props.availableWatchers
        : Array.isArray(window.Speculum?.availableWatchers)
          ? window.Speculum.availableWatchers
          : null,
);
const navFilter = ref('');

provide(ALERT_KEY, alert);
provide('autoLoadsNewEntries', autoLoadsNewEntries);

const navItems = [
    { to: '/requests', label: 'Requests', watcher: 'requests', group: 1 },
    { to: '/commands', label: 'Commands', watcher: 'commands', group: 1 },
    { to: '/jobs', label: 'Jobs', watcher: 'jobs', group: 1 },
    { to: '/batches', label: 'Batches', watcher: 'batches', group: 2 },
    { to: '/broadcasts', label: 'Broadcasts', watcher: 'broadcasts', group: 2 },
    { to: '/blazecast', label: 'BlazeCast', watcher: 'blazecast', group: 2 },
    { to: '/cache', label: 'Cache', watcher: 'cache', group: 2 },
    { to: '/events', label: 'Events', watcher: 'events', group: 2 },
    { to: '/exceptions', label: 'Exceptions', watcher: 'exceptions', group: 2 },
    { to: '/http-clients', label: 'HTTP Client', watcher: 'http-clients', group: 2 },
    { to: '/logs', label: 'Logs', watcher: 'logs', group: 2 },
    { to: '/mail', label: 'Mail', watcher: 'mail', group: 2 },
    { to: '/models', label: 'Models', watcher: 'models', group: 2 },
    { to: '/mongo', label: 'Mongo', watcher: 'mongo', group: 2 },
    { to: '/notifications', label: 'Notifications', watcher: 'notifications', group: 2 },
    { to: '/queries', label: 'Queries', watcher: 'queries', group: 2 },
    { to: '/schedule', label: 'Schedule', watcher: 'schedule', group: 2 },
    { to: '/vardumps', label: 'Var Dumps', watcher: 'vardumps', group: 2 },
    { to: '/views', label: 'Views', watcher: 'views', group: 2 },
];

const visibleNavItems = computed(() => {
    const available = Array.isArray(metaWatchers.value) && metaWatchers.value.length > 0
        ? new Set(metaWatchers.value)
        : null;

    const builtIn = available
        ? navItems.filter((item) => available.has(item.watcher) || available.has(item.to.replace('/', '')))
        : navItems;

    const builtInWatchers = new Set(builtIn.map((item) => item.watcher));
    const builtInPaths = new Set(builtIn.map((item) => item.to));

    const extensions = getExtensionNavItems().filter((item) => {
        if (builtInWatchers.has(item.watcher) || builtInPaths.has(item.to)) {
            return false;
        }

        if (!available) {
            return true;
        }

        return available.has(item.watcher) || available.has(item.key);
    });

    const merged = [...builtIn, ...extensions];
    const group1 = merged.filter((item) => item.group === 1);
    const group2 = merged
        .filter((item) => item.group === 2)
        .sort((a, b) => a.label.localeCompare(b.label));
    const other = merged.filter((item) => item.group !== 1 && item.group !== 2);

    return [...group1, ...group2, ...other];
});

const filteredNavItems = computed(() => {
    const query = navFilter.value.trim().toLowerCase();
    if (query === '') {
        return visibleNavItems.value;
    }

    return visibleNavItems.value.filter(
        (item) =>
            item.label.toLowerCase().includes(query) ||
            item.watcher.toLowerCase().includes(query) ||
            item.to.toLowerCase().includes(query),
    );
});

function goToFilteredNavItem() {
    const first = filteredNavItems.value[0];
    if (!first) {
        return;
    }

    router.push(first.to);
    navFilter.value = '';
}

function onNavFilterKeydown(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        goToFilteredNavItem();
    }

    if (event.key === 'Escape') {
        navFilter.value = '';
    }
}
function autoLoadNewEntries() {
    autoLoadsNewEntries.value = !autoLoadsNewEntries.value;
    localStorage[LOCALSTORAGE_AUTOLOAD_KEY] = Number(autoLoadsNewEntries.value);
}

function toggleRecording() {
    api.post('/toggle-recording');
    window.Speculum.recording = !window.Speculum.recording;
    recording.value = !recording.value;
}

function clearEntries(shouldConfirm = true) {
    if (shouldConfirm && !confirm('Are you sure you want to delete all Speculum data?')) {
        return;
    }

    api.delete('/entries').then(() => location.reload());
}

function keydownListener(event) {
    if (event.metaKey && event.key === 'k') {
        clearEntries(false);
    }
}

function clearAlert() {
    alert.type = null;
    alert.autoClose = false;
    alert.message = '';
    alert.confirmationProceed = null;
    alert.confirmationCancel = null;
}

function openSetup() {
    setupOpen.value = true;
}

function onSetupSaved() {
    hasLocalRoot.value = Boolean(localRoot());
}

onMounted(() => {
    window.addEventListener('keydown', keydownListener);

    if (!Array.isArray(metaWatchers.value)) {
        api
            .get('/meta')
            .then((response) => {
                metaWatchers.value = response.data.availableWatchers || null;
            })
            .catch(() => {
                metaWatchers.value = null;
            });
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', keydownListener);
});

defineExpose({
    alert,
    autoLoadsNewEntries,
    autoLoadNewEntries,
});
</script>

<template>
    <div>
        <Alert
            v-if="alert.type"
            :message="alert.message"
            :type="alert.type"
            :auto-close="alert.autoClose"
            :confirmation-proceed="alert.confirmationProceed"
            :confirmation-cancel="alert.confirmationCancel"
            @closed="clearAlert"
        />

        <SetupModal
            v-if="setupOpen"
            @close="setupOpen = false"
            @saved="onSetupSaved"
        />

        <div class="container mb-5">
            <div class="d-flex align-items-stretch py-4 header">
                <RouterLink
                    to="/"
                    class="logo d-flex align-items-center"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 80 80"
                        aria-hidden="true"
                    >
                        <path
                            class="fill-primary"
                            fill-rule="evenodd"
                            d="M40 6C23.431 6 10 19.431 10 36c0 14.012 9.58 25.74 22.5 29.05V72h-5.5a3.5 3.5 0 100 7h26a3.5 3.5 0 100-7H47.5v-6.95C60.42 61.74 70 50.012 70 36 70 19.431 56.569 6 40 6zm0 10c11.046 0 20 8.954 20 20s-8.954 20-20 20-20-8.954-20-20 8.954-20 20-20zm-8 8a2.5 2.5 0 012.5-2.5h7a2.5 2.5 0 010 5h-7A2.5 2.5 0 0132 24z"
                        />
                    </svg>
                    <h4 class="mb-0 ms-3">
                        <strong>CakePHP</strong> Speculum
                    </h4>
                </RouterLink>

                <button
                    class="btn btn-muted ms-auto me-3 d-flex align-items-center py-2"
                    title="Setup (local project ROOT)"
                    @click.prevent="openSetup"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        class="icon"
                        fill="currentColor"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M8.34 1.804A1 1 0 019.32 1h1.36a1 1 0 01.98.804l.295 1.473c.497.144.971.342 1.416.587l1.25-.834a1 1 0 011.262.125l.962.962a1 1 0 01.125 1.262l-.834 1.25c.245.445.443.919.587 1.416l1.473.294a1 1 0 01.804.98v1.361a1 1 0 01-.804.98l-1.473.295a6.95 6.95 0 01-.587 1.416l.834 1.25a1 1 0 01-.125 1.262l-.962.962a1 1 0 01-1.262.125l-1.25-.834a6.953 6.953 0 01-1.416.587l-.294 1.473a1 1 0 01-.98.804H9.32a1 1 0 01-.98-.804l-.295-1.473a6.957 6.957 0 01-1.416-.587l-1.25.834a1 1 0 01-1.262-.125l-.962-.962a1 1 0 01-.125-1.262l.834-1.25a6.957 6.957 0 01-.587-1.416l-1.473-.294A1 1 0 011 10.68V9.32a1 1 0 01.804-.98l1.473-.295c.144-.497.342-.971.587-1.416l-.834-1.25a1 1 0 01.125-1.262l.962-.962A1 1 0 015.38 3.03l1.25.834a6.957 6.957 0 011.416-.587l.294-1.473zM10 13a3 3 0 100-6 3 3 0 000 6z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </button>

                <button
                    class="btn btn-muted me-3 d-flex align-items-center py-2"
                    :title="recording ? 'Pause recording' : 'Resume recording'"
                    @click.prevent="toggleRecording"
                >
                    <svg
                        v-if="recording"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        class="icon"
                        fill="currentColor"
                    >
                        <path
                            d="M5.75 3a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75A.75.75 0 007.25 3h-1.5zM12.75 3a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75a.75.75 0 00-.75-.75h-1.5z"
                        />
                    </svg>
                    <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        class="icon"
                        fill="currentColor"
                    >
                        <path
                            d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"
                        />
                    </svg>
                </button>

                <button
                    class="btn btn-muted me-3 d-flex align-items-center py-2"
                    title="Clear entries"
                    @click.prevent="clearEntries"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        class="icon"
                        fill="currentColor"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </button>

                <button
                    class="btn btn-muted me-3 d-flex align-items-center py-2"
                    :class="{ active: autoLoadsNewEntries }"
                    title="Auto load entries"
                    @click.prevent="autoLoadNewEntries"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        class="icon"
                        fill="currentColor"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V2.929a.75.75 0 00-1.5 0V5.36l-.31-.31A7 7 0 003.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0113.89 6.11l.311.31h-2.432a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </button>

                <SchemeToggler class="me-3" />

                <RouterLink
                    to="/monitored-tags"
                    class="btn btn-muted d-flex align-items-center py-2"
                    title="Monitoring"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        class="icon"
                        fill="currentColor"
                    >
                        <path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
                        <path
                            fill-rule="evenodd"
                            d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </RouterLink>
            </div>

            <div class="row mt-4">
                <div class="col-2 sidebar">
                    <div class="form-control-with-icon sidebar-nav-filter mb-3">
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
                            v-model="navFilter"
                            type="search"
                            class="form-control w-100"
                            placeholder="Filter menu"
                            autocomplete="off"
                            @keydown="onNavFilterKeydown"
                        >
                    </div>

                    <ul class="nav flex-column">
                        <li
                            v-for="(item, index) in filteredNavItems"
                            :key="item.to"
                            class="nav-item"
                            :class="{
                                'mt-3':
                                    index > 0 &&
                                    item.group !== filteredNavItems[index - 1].group &&
                                    navFilter.trim() === '',
                            }"
                        >
                            <RouterLink
                                active-class="active"
                                :to="item.to"
                                class="nav-link d-flex align-items-center"
                                @click="navFilter = ''"
                            >
                                <span>{{ item.label }}</span>
                            </RouterLink>
                        </li>
                        <li
                            v-if="navFilter.trim() !== '' && filteredNavItems.length === 0"
                            class="nav-item px-2 text-muted small"
                        >
                            No matches
                        </li>
                    </ul>
                </div>

                <div class="col-10">
                    <RouterView />
                </div>
            </div>
        </div>
    </div>
</template>
