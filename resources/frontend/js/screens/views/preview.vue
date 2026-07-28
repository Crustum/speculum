<script setup>
import { ref } from 'vue';
import { useEntryStyles } from '@/composables/useEntryStyles';
import { relativeToRoot, editorHref } from '@/utils/projectPath';

const { composerTypeClass } = useEntryStyles();

const currentTab = ref('data');
</script>

<template>
    <preview-screen
        :id="$route.params.id"
        title="View Action"
        resource="views"
    >
        <template #table-parameters="slotProps">
            <attribute-row
                title="View"
                :value="slotProps.entry.content.name"
            />

            <attribute-row
                title="Path"
                code
                :href="editorHref(slotProps.entry.content.path, 1, slotProps.entry.content.editor_url)"
                :value="relativeToRoot(slotProps.entry.content.path)"
            />
        </template>

        <template #after-attributes-card="slotProps">
            <div>
                <div
                    v-if="slotProps.entry.content.data"
                    class="card mt-5"
                >
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a
                                class="nav-link"
                                :class="{ active: currentTab == 'data' }"
                                href="#"
                                @click.prevent="currentTab = 'data'"
                            >Data</a>
                        </li>
                        <li
                            v-if="slotProps.entry.content.composers"
                            class="nav-item"
                        >
                            <a
                                class="nav-link"
                                :class="{ active: currentTab == 'composers' }"
                                href="#"
                                @click.prevent="currentTab = 'composers'"
                            >Composers</a>
                        </li>
                    </ul>
                    <div>
                        <!-- View Payload -->
                        <div
                            v-show="currentTab == 'data'"
                            class="code-bg p-4 mb-0 text-white"
                        >
                            <copy-clipboard :data="slotProps.entry.content.data">
                                <vue-json-pretty :data="slotProps.entry.content.data" />
                            </copy-clipboard>
                        </div>

                        <!-- View Composers -->
                        <table
                            v-show="currentTab == 'composers'"
                            class="table table-hover mb-0"
                        >
                            <thead>
                                <tr>
                                    <th>Composer</th>
                                    <th>Type</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="(composer, key) in slotProps.entry.content.composers"
                                    :key="key"
                                >
                                    <td :title="composer.name">
                                        {{ composer.name }}
                                    </td>
                                    <td class="table-fit">
                                        <span
                                            class="badge"
                                            :class="'badge-' + composerTypeClass(composer.type)"
                                        >
                                            {{ composer.type }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </preview-screen>
</template>
