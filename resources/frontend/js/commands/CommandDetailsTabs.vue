<script setup>
import { computed, ref, watch } from 'vue';
import './formatters/definitions';
import { resolveCommandFormatterTabs } from './formatters/registry';
import CommandFormatterPane from './CommandFormatterPane.vue';

const props = defineProps({
    entry: {
        type: Object,
        required: true,
    },
});

const currentTab = ref('arguments');

const tabs = computed(() => {
    const formatterTabs = resolveCommandFormatterTabs(props.entry);

    return [
        { id: 'arguments', label: 'Arguments', kind: 'builtin' },
        { id: 'options', label: 'Options', kind: 'builtin' },
        ...formatterTabs.map((tab) => ({
            id: tab.id,
            label: tab.label,
            kind: 'formatter',
            result: tab.result,
        })),
    ];
});

const activeFormatter = computed(() =>
    tabs.value.find((tab) => tab.id === currentTab.value && tab.kind === 'formatter') || null,
);

watch(
    () => props.entry?.id,
    () => {
        currentTab.value = 'arguments';
    },
);

watch(tabs, (next) => {
    if (!next.some((tab) => tab.id === currentTab.value)) {
        currentTab.value = 'arguments';
    }
});
</script>

<template>
    <div class="card mt-5 overflow-hidden">
        <ul class="nav nav-pills">
            <li
                v-for="tab in tabs"
                :key="tab.id"
                class="nav-item"
            >
                <a
                    class="nav-link"
                    :class="{ active: currentTab == tab.id }"
                    href="#"
                    @click.prevent="currentTab = tab.id"
                >{{ tab.label }}</a>
            </li>
        </ul>
        <div>
            <div
                v-if="currentTab === 'arguments' || currentTab === 'options'"
                class="code-bg p-4 mb-0 text-white"
            >
                <copy-clipboard :data="entry.content[currentTab]">
                    <vue-json-pretty :data="entry.content[currentTab]" />
                </copy-clipboard>
            </div>
            <div
                v-else-if="activeFormatter"
                class="code-bg p-4 mb-0 text-white"
            >
                <CommandFormatterPane :result="activeFormatter.result" />
            </div>
        </div>
    </div>
</template>
