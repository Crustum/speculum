<script setup>
import { computed, ref, watch, onMounted } from 'vue';
import { relatedTabDefinitions } from './related/definitions';
import { getExtensionRelatedDefinitions } from '../extensions/registry';

const props = defineProps({
    entry: { type: Object, default: null },
    batch: { type: Array, default: () => [] },
});

const currentTab = ref('');
const dropdownOpen = ref(false);

const batchList = computed(() => props.batch || []);
const currentEntryId = computed(() => props.entry?.id ?? null);
const entryPointTypes = ['request', 'command'];

function excludeCurrent(items) {
    const id = currentEntryId.value;
    if (id == null) {
        return items;
    }

    return items.filter((item) => item.id !== id);
}

const allRelatedDefinitions = computed(() => {
    const builtInTypes = new Set(relatedTabDefinitions.map((definition) => definition.type));
    const extensions = getExtensionRelatedDefinitions().filter(
        (definition) => !builtInTypes.has(definition.type),
    );

    return [...relatedTabDefinitions, ...extensions];
});

const groups = computed(() =>
    allRelatedDefinitions.value
        .map((definition) => ({
            type: definition.type,
            title: definition.title,
            component: definition.component,
            items: excludeCurrent(batchList.value.filter(definition.match)),
        }))
        .filter((group) => group.items.length > 0)
        .sort((a, b) => a.title.localeCompare(b.title))
);

const tabs = computed(() =>
    groups.value.map((group) => ({
        title: group.title,
        type: group.type,
        count: group.items.length,
    }))
);

const separateTabs = computed(() => tabs.value.slice(0, 7));
const dropdownTabs = computed(() => tabs.value.slice(7));
const dropdownTabSelected = computed(() =>
    dropdownTabs.value.map((tab) => tab.type).includes(currentTab.value)
);

const hasRelatedEntries = computed(
    () => excludeCurrent(batchList.value.filter((item) => !entryPointTypes.includes(item.type))).length > 0
);

function activateFirstTab() {
    if (window.location.hash) {
        const hash = window.location.hash.substring(1);
        if (tabs.value.some((tab) => tab.type === hash)) {
            currentTab.value = hash;
            return;
        }
    }

    currentTab.value = tabs.value[0]?.type || '';
}

function activateTab(tab) {
    currentTab.value = tab;
    dropdownOpen.value = false;

    if (window.history.replaceState) {
        window.history.replaceState(null, null, '#' + currentTab.value);
    }
}

watch(
    () => [props.entry, props.batch],
    () => activateFirstTab(),
    { deep: true }
);

onMounted(activateFirstTab);
</script>

<template>
    <div
        v-if="hasRelatedEntries"
        class="card overflow-hidden mt-5 related-entries"
    >
        <ul class="nav nav-pills">
            <li
                v-for="tab in separateTabs"
                :key="tab.type"
                class="nav-item"
            >
                <a
                    v-if="tab.count"
                    class="nav-link"
                    :class="{ active: currentTab == tab.type }"
                    href="#"
                    @click.prevent="activateTab(tab.type)"
                >
                    {{ tab.title }} ({{ tab.count }})
                </a>
            </li>
            <li
                v-if="dropdownTabs.length"
                class="nav-item dropdown"
            >
                <a
                    class="nav-link dropdown-toggle"
                    :class="{ active: dropdownTabSelected }"
                    href="#"
                    role="button"
                    @click.prevent="dropdownOpen = !dropdownOpen"
                >
                    More
                </a>
                <div
                    class="dropdown-menu"
                    :class="{ show: dropdownOpen }"
                >
                    <a
                        v-for="tab in dropdownTabs"
                        :key="tab.type"
                        class="dropdown-item"
                        :class="{ active: currentTab == tab.type }"
                        href="#"
                        @click.prevent="activateTab(tab.type)"
                    >
                        {{ tab.title }} ({{ tab.count }})
                    </a>
                </div>
            </li>
        </ul>

        <div>
            <div
                v-for="group in groups"
                v-show="currentTab == group.type"
                :key="group.type"
            >
                <component
                    :is="group.component"
                    :items="group.items"
                />
            </div>
        </div>
    </div>
</template>
