<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    data: {
        type: [String, Object, Array, Number, Boolean, null],
        default: null,
    },
});

const copying = ref(false);

const copyText = computed(() => {
    if (typeof props.data === 'string') {
        return props.data;
    }

    return JSON.stringify(props.data, null, '\t');
});

async function handleCopy() {
    try {
        await navigator.clipboard.writeText(copyText.value);
        copying.value = true;
        setTimeout(() => {
            copying.value = false;
        }, 1000);
    } catch (_error) {
        copying.value = false;
    }
}
</script>

<template>
    <div class="position-relative">
        <div class="copy-to-clipboard">
            <span
                v-if="copying"
                style="color: #ffffff"
            >
                <svg
                    fill="currentColor"
                    viewBox="0 0 20 20"
                    style="width: 1.25rem; height: 1.25rem"
                >
                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                    <path
                        fill-rule="evenodd"
                        d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm9.707 5.707a1 1 0 00-1.414-1.414L9 12.586l-1.293-1.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                        clip-rule="evenodd"
                    />
                </svg>
            </span>

            <a
                v-else
                href="#"
                @click.prevent="handleCopy"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    style="width: 1.25rem; height: 1.25rem"
                >
                    <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
                    <path
                        d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"
                    />
                </svg>
            </a>
        </div>
        <slot />
    </div>
</template>
