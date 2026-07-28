<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { hideCssModal, showCssModal } from '../utils/cssModal';
import {
    localRoot,
    setLocalRoot,
} from '../utils/projectPath';

const emit = defineEmits(['close', 'saved']);

const modalElement = ref(null);
const draftRoot = ref(localRoot());

function close() {
    hideCssModal(modalElement.value, () => {
        emit('close');
    });
}

function save() {
    setLocalRoot(draftRoot.value);
    emit('saved');
    close();
}

function clearLocal() {
    draftRoot.value = '';
    setLocalRoot('');
    emit('saved');
    close();
}

onMounted(() => {
    showCssModal(modalElement.value, { onEscape: close });
});

onBeforeUnmount(() => {
    hideCssModal(modalElement.value);
});
</script>

<template>
    <div
        id="setupModal"
        ref="modalElement"
        class="modal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="setupModalLabel"
        aria-hidden="true"
    >
        <div
            class="modal-dialog modal-lg"
            role="document"
        >
            <div class="modal-content">
                <div class="modal-header">
                    <h5
                        id="setupModalLabel"
                        class="modal-title"
                    >
                        Setup
                    </h5>
                    <button
                        type="button"
                        class="btn-close"
                        aria-label="Close"
                        @click="close"
                    />
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Map this Speculum server’s project ROOT to your local checkout so IDE links open the right files (useful when browsing a remote Speculum).
                    </p>

                    <div class="mb-2">
                        <label
                            class="form-label"
                            for="speculum-local-root"
                        >
                            Local project ROOT
                        </label>
                        <input
                            id="speculum-local-root"
                            v-model="draftRoot"
                            type="text"
                            class="form-control"
                            placeholder="/dev/projects/my-app"
                            autocomplete="off"
                            @keydown.enter.prevent="save"
                        >
                        <div class="form-text">
                            Stored in this browser’s localStorage. Takes priority over the server ROOT for IDE links.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        @click="clearLocal"
                    >
                        Use server ROOT
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        @click="save"
                    >
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
