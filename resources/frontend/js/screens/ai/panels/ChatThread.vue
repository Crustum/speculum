<script setup>
import { computed } from 'vue';

const props = defineProps({
    payload: { type: Object, required: true },
});

function roleValue(role) {
    if (!role) {
        return null;
    }
    if (typeof role === 'string') {
        return role;
    }
    const propsObj = role.properties ?? role;

    return propsObj.value ?? propsObj.name ?? role.name ?? null;
}

// Messages arrive as { class: UserMessage|AssistantMessage, properties: { role, content, toolCalls, toolResults } }.
// Flatten to { role, content, toolCalls, toolResults } so the thread can render them uniformly.
function normalizeMessage(msg) {
    if (!msg || typeof msg !== 'object') {
        return null;
    }
    const propsObj = msg.properties ?? msg;
    const content = msg.content ?? propsObj.content ?? null;
    const role = roleValue(msg.role ?? propsObj.role) ?? 'assistant';

    return {
        role,
        content: typeof content === 'string' ? content : (content ? JSON.stringify(content) : ''),
        toolCalls: propsObj.toolCalls ?? [],
        toolResults: propsObj.toolResults ?? [],
    };
}

const messages = computed(() => {
    const out = [];
    const p = props.payload ?? {};

    if (Array.isArray(p.messages)) {
        for (const m of p.messages) {
            const norm = normalizeMessage(m);
            if (norm) {
                out.push(norm);
            }
        }
    }

    const response = p.response ?? p.responseData ?? null;
    if (response && typeof response === 'object') {
        if (Array.isArray(response.messages)) {
            for (const m of response.messages) {
                const norm = normalizeMessage(m);
                if (norm) {
                    out.push(norm);
                }
            }
        }
        if (Array.isArray(response.steps)) {
            for (const step of response.steps) {
                const stepProps = step?.properties ?? step;
                if (stepProps?.text) {
                    out.push({
                        role: 'assistant',
                        content: stepProps.text,
                        toolCalls: stepProps.tool_calls ?? stepProps.toolCalls ?? [],
                        toolResults: stepProps.tool_results ?? stepProps.toolResults ?? [],
                    });
                }
            }
        }
    }

    return out;
});

// Surface token usage from the response when present (OpenAI-style `usage`).
const usage = computed(() => {
    const response = props.payload?.response ?? null;
    const u = response?.usage ?? null;
    const propsObj = u?.properties ?? u;

    if (!propsObj || typeof propsObj !== 'object') {
        return null;
    }

    return [
        `prompt: ${propsObj.prompt_tokens ?? '?'}`,
        `completion: ${propsObj.completion_tokens ?? '?'}`,
        `cache read: ${propsObj.cache_read_input_tokens ?? '?'}`,
        `cache write: ${propsObj.cache_write_input_tokens ?? '?'}`,
    ].join(' · ');
});

const hasChat = computed(() => messages.value.length > 0);
</script>

<template>
    <div
        v-if="hasChat"
        class="card mt-5"
    >
        <div class="card-header">
            Conversation
        </div>
        <div class="card-body d-flex flex-column gap-2">
            <div
                v-for="(m, i) in messages"
                :key="i"
                class="p-2 rounded"
                :class="m.role === 'user' ? 'align-self-end bg-primary text-white' : 'align-self-start bg-light border'"
            >
                <div class="small text-uppercase opacity-75">
                    {{ m.role }}
                </div>
                <div style="white-space: pre-wrap;">
                    {{ m.content }}
                </div>
            </div>
            <div
                v-if="usage"
                class="small text-muted mt-1"
            >
                {{ usage }}
            </div>
        </div>
    </div>
</template>
