<script setup>
import { computed, ref } from 'vue';
import AgentPanel from './panels/AgentPanel.vue';
import GenerationPanel from './panels/GenerationPanel.vue';
import ToolPanel from './panels/ToolPanel.vue';
import StorePanel from './panels/StorePanel.vue';
import FailoverPanel from './panels/FailoverPanel.vue';
import FailurePanel from './panels/FailurePanel.vue';
import JsonCard from './panels/JsonCard.vue';
import { aiCategory } from '@/utils/aiCategory';

const props = defineProps({
    entry: { type: Object, required: true },
});

const panels = {
    agent: AgentPanel,
    generation: GenerationPanel,
    tool: ToolPanel,
    store: StorePanel,
    file: StorePanel,
    failover: FailoverPanel,
};

// Prefer the stored category; fall back to deriving it from the event name so
// entries recorded before `category` was persisted still reach the right panel.
const category = computed(() => aiCategory(props.entry));
const categoryPanel = computed(() => panels[category.value] ?? null);

const currentTab = ref('raw');
</script>

<template>
    <preview-screen
        :id="$route.params.id"
        title="AI Event Details"
        resource="ai"
    >
        <template #table-parameters="slotProps">
            <attribute-row title="Category">
                <span class="badge badge-info text-uppercase">
                    {{ category }}
                </span>
                <flag-badge
                    :show="!!slotProps.entry.content.failed"
                    label="Failed"
                    variant="danger"
                />
            </attribute-row>

            <attribute-row title="Event">
                {{ slotProps.entry.content.name }}
            </attribute-row>

            <attribute-row
                v-if="slotProps.entry.content.invocationId"
                title="Invocation ID"
            >
                <router-link
                    :to="{ name: 'ai-invocation', params: { id: slotProps.entry.content.invocationId } }"
                    class="control-action"
                    :title="'Show full invocation history'"
                >
                    {{ slotProps.entry.content.invocationId }}
                </router-link>
            </attribute-row>

            <attribute-row
                v-if="slotProps.entry.content.provider"
                title="Provider"
                :value="slotProps.entry.content.provider"
            />

            <attribute-row
                v-if="slotProps.entry.content.model"
                title="Model"
                :value="slotProps.entry.content.model"
            />

            <attribute-row
                v-if="slotProps.entry.content.step != null"
                title="Step"
                :value="String(slotProps.entry.content.step)"
            />

            <attribute-row
                v-if="slotProps.entry.content.is_final != null"
                title="Final Step"
            >
                {{ slotProps.entry.content.is_final ? 'Yes' : 'No' }}
            </attribute-row>

            <attribute-row
                v-if="slotProps.entry.content.duration != null"
                title="Duration"
            >
                {{ slotProps.entry.content.duration }}ms
                <flag-badge
                    :show="!!slotProps.entry.content.slow"
                    label="Slow"
                    variant="danger"
                />
            </attribute-row>

            <attribute-row
                v-if="slotProps.entry.content.summary"
                title="Summary"
                :value="slotProps.entry.content.summary"
            />
        </template>

        <template #after-attributes-card="slotProps">
            <div class="card mt-5 overflow-hidden">
                <ul class="nav nav-pills">
                    <li class="nav-item">
                        <a
                            class="nav-link"
                            :class="{ active: currentTab === 'raw' }"
                            href="#"
                            @click.prevent="currentTab = 'raw'"
                        >Raw</a>
                    </li>
                    <li class="nav-item">
                        <a
                            class="nav-link"
                            :class="{ active: currentTab === 'specific' }"
                            href="#"
                            @click.prevent="currentTab = 'specific'"
                        >Extracted Data</a>
                    </li>
                </ul>

                <div v-show="currentTab === 'raw'">
                    <JsonCard
                        title="Raw Event Data"
                        :data="slotProps.entry.content.payload"
                    />
                </div>

                <div v-show="currentTab === 'specific'">
                    <component
                        :is="categoryPanel"
                        v-if="categoryPanel"
                        :entry="slotProps.entry"
                    />
                    <FailurePanel
                        v-if="slotProps.entry.content.failed"
                        :entry="slotProps.entry"
                    />
                </div>
            </div>
        </template>
    </preview-screen>
</template>
