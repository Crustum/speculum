<script setup>
import { nextTick, onMounted, ref } from 'vue';
import api from '@/utils/api';
import { useAlert } from '@/composables/useAlert';
import { useHelpers } from '@/composables/useHelpers';
import { hideCssModal, showCssModal } from '@/utils/cssModal';

const { truncate } = useHelpers();
const { alertConfirm } = useAlert();

const tags = ref([]);
const ready = ref(false);
const newTag = ref('');
const modalElement = ref(null);

function removeTag(tag) {
    alertConfirm('Are you sure you want to remove this tag?', () => {
        tags.value = tags.value.filter((item) => item !== tag);
        api.post('/monitored-tags/delete', { tag });
    });
}

function openNewTagModal() {
    showCssModal(modalElement.value, { onEscape: cancelNewTag });
    nextTick(() => {
        document.getElementById('newTagInput')?.focus();
    });
}

function monitorNewTag() {
    if (newTag.value.length) {
        api.post('/monitored-tags', { tag: newTag.value });
        tags.value.push(newTag.value);
    }

    hideCssModal(modalElement.value);
    newTag.value = '';
}

function cancelNewTag() {
    hideCssModal(modalElement.value);
    newTag.value = '';
}

onMounted(() => {
    document.title = 'Monitoring - Speculum';

    api.get('/monitored-tags')
        .then((response) => {
            tags.value = response.data.tags || [];
            ready.value = true;
        })
        .catch(() => {
            tags.value = [];
            ready.value = true;
        });
});
</script>

<template>
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h2 class="h6 m-0">
                Monitoring
            </h2>
            <button
                class="btn btn-primary"
                @click.prevent="openNewTagModal"
            >
                Monitor
            </button>
        </div>

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
            v-if="ready && tags.length == 0"
            class="d-flex align-items-center justify-content-center card-bg-secondary p-5 bottom-radius"
        >
            <span>No tags are currently being monitored.</span>
        </div>

        <table
            v-if="ready && tags.length > 0"
            class="table table-hover mb-0"
        >
            <thead>
                <tr>
                    <th>Tag</th>
                    <th />
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="tag in tags"
                    :key="tag"
                >
                    <td>{{ truncate(tag, 140) }}</td>
                    <td class="table-fit">
                        <a
                            href="#"
                            class="control-action"
                            @click.prevent="removeTag(tag)"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                            >
                                <path d="M6 2l2-2h4l2 2h4v2H2V2h4zM3 6h14l-1 14H4L3 6zm5 2v10h1V8H8zm3 0v10h1V8h-1z" />
                            </svg>
                        </a>
                    </td>
                </tr>
            </tbody>
        </table>

        <div
            id="addTagModel"
            ref="modalElement"
            class="modal"
            tabindex="-1"
            role="dialog"
            aria-hidden="true"
        >
            <div
                class="modal-dialog"
                role="document"
            >
                <div class="modal-content">
                    <div class="modal-header">
                        Monitor New Tag
                    </div>
                    <div class="modal-body">
                        <input
                            id="newTagInput"
                            v-model="newTag"
                            type="text"
                            class="form-control"
                            placeholder="Project:6352"
                            @keyup.enter="monitorNewTag"
                        >
                    </div>
                    <div class="modal-footer justify-content-start flex-row-reverse">
                        <button
                            class="btn btn-primary"
                            @click="monitorNewTag"
                        >
                            Monitor
                        </button>
                        <button
                            class="btn"
                            @click="cancelNewTag"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
