// Helpers for rendering AI tool calls (Ai.invokingTool / Ai.toolInvoked /
// Ai.toolFailed / Ai.toolApprovalRequested / Ai.toolApprovalResolved).
//
// Backend (AiWatcher) writes resolved fields for new entries:
// content.tool_name (resolved name, e.g. `ask_user`), content.tool_class (inner
// class), content.tool_wrapper (decorator, e.g. `EventedTool`),
// content.tool_invocation_id. Older entries lack them, so every helper falls
// back to the serialized payload.tool ({ class, properties }) where the
// decorator's wrapped tool sits in `properties.inner`.
import { aiCategory } from './aiCategory';

export function payloadTool(entry) {
    return entry?.content?.payload?.tool ?? {};
}

// First tool result of approval-style payloads (Ai.toolApprovalRequested /
// Ai.toolApprovalResolved carry `payload.toolResults` with no `payload.tool`).
// Accepts serialized `{class, properties}` and plain shapes; returns
// `{name, arguments, result, id}` with nulls where unknown.
export function payloadToolResult(entry) {
    const payload = entry?.content?.payload ?? {};
    const list = payload.toolResults ?? payload.tool_results ?? null;
    const raw = Array.isArray(list)
        ? list[0] ?? null
        : (payload.toolResult ?? payload.tool_result ?? null);

    if (!raw || typeof raw !== 'object') {
        return null;
    }

    const props = raw.properties ?? raw;
    if (!props || typeof props !== 'object') {
        return null;
    }

    const id = props.result_id ?? props.id ?? raw.id ?? null;

    return {
        name: typeof props.name === 'string' && props.name !== '' ? props.name : null,
        arguments: isPlainObject(props.arguments) ? props.arguments : {},
        result: props.result ?? null,
        id: typeof id === 'string' || typeof id === 'number' ? String(id) : null,
    };
}

export function innerToolClass(entry) {
    const content = entry?.content ?? {};

    if (content.tool_class) {
        return content.tool_class;
    }

    const tool = payloadTool(entry);

    return tool?.properties?.inner?.class ?? tool.class ?? null;
}

export function toolDisplayName(entry) {
    const content = entry?.content ?? {};

    return shortClassName(
        content.tool_name
        || content.tool_class
        || innerToolClass(entry)
        || payloadToolResult(entry)?.name
        || content.tool
        || null,
    );
}

export function toolWrapperName(entry) {
    const content = entry?.content ?? {};

    if (content.tool_wrapper) {
        return content.tool_wrapper;
    }

    const tool = payloadTool(entry);
    const innerClass = tool?.properties?.inner?.class;

    if (innerClass && tool.class && tool.class !== innerClass) {
        return tool.class;
    }

    return null;
}

export function toolInvocationId(entry) {
    return entry?.content?.tool_invocation_id
        ?? entry?.content?.payload?.toolInvocationId
        ?? payloadToolResult(entry)?.id
        ?? null;
}

function isPlainObject(value) {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

// One-line human summary of a single argument value. Scalars inline,
// arrays/objects collapsed with their size so history rows stay scannable;
// full data remains available in the JSON card below.
export function summarizeValue(value, maxLen = 120) {
    if (value === null || value === undefined) {
        return '—';
    }

    if (typeof value === 'string') {
        const flat = value.replace(/\s+/g, ' ').trim();

        return flat.length > maxLen ? flat.slice(0, maxLen - 1) + '…' : flat;
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    if (Array.isArray(value)) {
        if (value.length === 0) {
            return '[]';
        }

        if (value.length <= 5 && value.every((item) => typeof item === 'string' || typeof item === 'number' || typeof item === 'boolean')) {
            const joined = value.map((item) => summarizeValue(item, 60)).join(', ');

            return joined.length > maxLen ? joined.slice(0, maxLen - 1) + '…' : `[${joined}]`;
        }

        return `[${value.length} item${value.length === 1 ? '' : 's'}]`;
    }

    if (isPlainObject(value)) {
        if (typeof value.class === 'string' && value.properties !== undefined) {
            const short = value.class.split('\\').pop();
            const keys = isPlainObject(value.properties) ? Object.keys(value.properties).length : 0;

            return `${short} {${keys} key${keys === 1 ? '' : 's'}}`;
        }

        const keys = Object.keys(value);

        if (keys.length <= 3) {
            const inner = keys.map((k) => `${k}: ${summarizeValue(value[k], 40)}`).join(', ');

            return `{${inner}}`;
        }

        return `{${keys.length} keys}`;
    }

    return String(value);
}

// Agent class (FQCN, e.g. App\Agent\ImplementationReviewAgent): watcher
// writes content.agent_class for new entries; legacy entries resolve it from
// the serialized payload agent (normalized to payload.agent by the watcher).
export function entryAgentClass(entry) {
    const content = entry?.content ?? {};

    if (typeof content.agent_class === 'string' && content.agent_class !== '') {
        return content.agent_class;
    }

    const agent = content.payload?.agent ?? content.payload?.prompt?.properties?.agent ?? null;

    return agent?.class ?? agent?.properties?.class ?? null;
}

// Plain arguments object of a tool entry (invoking side). Approval payloads
// carry them on the first tool result instead of `payload.arguments`.
export function entryArguments(entry) {
    const args = entry?.content?.payload?.arguments;

    if (args && typeof args === 'object' && !Array.isArray(args)) {
        return args;
    }

    return payloadToolResult(entry)?.arguments ?? {};
}

// Absolute `YYYY-MM-DD HH:mm:ss` for timeline timestamps (relative "N days ago"
// hides the actual time of long runs).
export function formatDateTime(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    const pad = (n) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}

// `key: value, …` summary of an arguments object for call lines.
// Short class name (after the last backslash) for display in call lines.
export function shortClassName(value) {
    if (typeof value !== 'string' || value === '') {
        return value;
    }

    const pos = value.lastIndexOf('\\');

    return pos === -1 ? value : value.slice(pos + 1);
}

// Full inner tool class when available (legacy payloads carry FQCN;
// new entries carry the short class from the watcher).
export function entryToolClass(entry) {
    const content = entry?.content ?? {};

    if (typeof content.tool_class === 'string' && content.tool_class !== '') {
        return content.tool_class;
    }

    const tool = content.payload?.tool ?? {};
    const inner = tool?.properties?.inner;

    return inner?.class ?? tool.class ?? content.tool ?? null;
}

export function summarizeArgs(args, maxLen = 60) {
    if (!args || typeof args !== 'object' || Array.isArray(args)) {
        return '';
    }

    return Object.keys(args).map((key) => `${key}: ${summarizeValue(args[key], maxLen)}`).join(', ');
}

// `name(key: value, …)` line in the spirit of Symfony's tool_calls macro.
export function toolCallLine(entry, maxPairs = 6) {
    const name = toolDisplayName(entry) ?? 'tool';
    const args = entryArguments(entry);

    if (!isPlainObject(args) || Object.keys(args).length === 0) {
        return `${name}()`;
    }

    const keys = Object.keys(args);
    if (keys.length <= maxPairs) {
        return `${name}(${summarizeArgs(args)})`;
    }

    const shown = keys.slice(0, maxPairs).map((k) => `${k}: ${summarizeValue(args[k], 60)}`).join(', ');

    return `${name}(${shown}, …+${keys.length - maxPairs})`;
}

// Failure line (`Class: message`) for agent/step/tool failures: the watcher
// stores content.exception for new entries, legacy payloads carry a
// serialized `payload.exception` ({class, properties} or plain).
export function entryException(entry) {
    const content = entry?.content ?? {};
    const raw = content.exception
        ?? content.payload?.exception?.properties
        ?? content.payload?.exception
        ?? null;

    if (!raw || typeof raw !== 'object') {
        return null;
    }

    const name = typeof raw.class === 'string' && raw.class !== '' ? raw.class : 'error';
    const message = typeof raw.message === 'string' && raw.message !== '' ? raw.message : '';
    const line = message !== '' ? `${name}: ${message}` : name;

    return line;
}

// Tool result as plain text when it is (or wraps) a string; otherwise null so
// the caller falls back to the JSON card. Approval payloads carry the text on
// the first tool result (`properties.result`).
export function resultText(entry) {
    const result = entry?.content?.payload?.result ?? payloadToolResult(entry)?.result;

    if (typeof result === 'string') {
        return result;
    }

    if (isPlainObject(result)) {
        for (const key of ['text', 'content', 'value', 'message']) {
            if (typeof result[key] === 'string') {
                return result[key];
            }
        }

        const props = result.properties;

        if (isPlainObject(props)) {
            for (const key of ['text', 'content', 'value', 'message']) {
                if (typeof props[key] === 'string') {
                    return props[key];
                }
            }
        }
    }

    return null;
}

// Conversation thread for an entry: [{role, content, tool_calls, tool_results}].
// Prefers watcher-extracted content.thread (new entries); falls back to
// parsing the serialized payload (legacy entries).
export function entryThread(entry) {
    const content = entry?.content ?? {};

    if (Array.isArray(content.thread)) {
        return content.thread;
    }

    const out = [];
    const payload = content.payload ?? {};

    if (Array.isArray(payload.messages)) {
        for (const message of payload.messages) {
            const row = normalizeMessage(message);
            if (row) {
                out.push(row);
            }
        }
    }

    if (typeof payload.prompt_text === 'string') {
        out.push({ role: 'prompt', content: payload.prompt_text, tool_calls: [], tool_results: [] });
    }

    const promptString = payload.prompt?.properties?.prompt
        ?? payload.prompt?.prompt
        ?? promptInputsText(payload.prompt);
    if (typeof promptString === 'string' && promptString.trim() !== '') {
        out.push({ role: 'prompt', content: promptString, tool_calls: [], tool_results: [] });
    }

    const response = payload.response ?? null;
    if (response && typeof response === 'object') {
        const props = response.properties ?? response;

        if (Array.isArray(props.messages)) {
            for (const message of props.messages) {
                const row = normalizeMessage(message);
                if (row) {
                    out.push(row);
                }
            }
        }
    }

    return out;
}

// Input texts of embeddings-style prompts (`properties.inputs`), joined for
// the thread fallback. New entries carry watcher-extracted `prompt_text`.
function promptInputsText(prompt) {
    const inputs = prompt?.properties?.inputs ?? prompt?.inputs ?? null;

    if (typeof inputs === 'string') {
        return inputs.trim() !== '' ? inputs : null;
    }

    if (Array.isArray(inputs)) {
        const texts = inputs
            .filter((text) => typeof text === 'string' && text.trim() !== '')
            .slice(0, 20);

        return texts.length ? texts.join('\n') : null;
    }

    return null;
}

// Embeddings shape ({count, dimensions}) for generation entries: prefers the
// watcher-extracted content.response_embeddings, falls back to measuring the
// serialized payload vectors (floats themselves never render).
export function entryEmbeddings(entry) {
    const content = entry?.content ?? {};
    const stored = content.response_embeddings;

    if (stored && typeof stored === 'object' && !Array.isArray(stored)) {
        return {
            count: typeof stored.count === 'number' ? stored.count : null,
            dimensions: typeof stored.dimensions === 'number' ? stored.dimensions : null,
        };
    }

    const response = content.payload?.response ?? null;
    const vectors = response?.properties?.embeddings ?? response?.embeddings ?? null;

    if (!Array.isArray(vectors) || vectors.length === 0) {
        return null;
    }

    const first = vectors[0];

    return {
        count: vectors.length,
        dimensions: Array.isArray(first) ? first.length : null,
    };
}

export function formatEmbeddings(info) {
    if (!info || typeof info.count !== 'number') {
        return null;
    }

    const vectors = `${info.count} vector${info.count === 1 ? '' : 's'}`;

    return typeof info.dimensions === 'number' ? `${vectors} × ${info.dimensions} dims` : vectors;
}

// Response text: watcher-extracted content.response_text first, serialized
// payload.response.properties.text as fallback.
export function entryResponseText(entry) {
    const content = entry?.content ?? {};

    if (typeof content.response_text === 'string') {
        return content.response_text;
    }

    const response = content.payload?.response ?? null;
    const text = response?.properties?.text ?? response?.text;

    return typeof text === 'string' ? text : null;
}

// Token usage as {prompt, completion, cache_read, cache_write, reasoning}.
// Handles watcher-extracted content.usage, flat serialized usage
// ({prompt_tokens, ...}) and {class, properties} wrapped usage.
export function entryUsage(entry) {
    const content = entry?.content ?? {};

    if (content.usage && typeof content.usage === 'object') {
        return content.usage;
    }

    const raw = content.payload?.response?.properties?.usage
        ?? content.payload?.response?.usage
        ?? null;
    const props = raw?.properties ?? raw;

    if (!props || typeof props !== 'object') {
        return null;
    }

    const pick = (snake, camel) => {
        if (typeof props[snake] === 'number') {
            return props[snake];
        }

        if (typeof props[camel] === 'number') {
            return props[camel];
        }

        return null;
    };

    const usage = {
        prompt: pick('prompt_tokens', 'promptTokens'),
        completion: pick('completion_tokens', 'completionTokens'),
        cache_read: pick('cache_read_input_tokens', 'cacheReadInputTokens'),
        cache_write: pick('cache_write_input_tokens', 'cacheWriteInputTokens'),
        reasoning: pick('reasoning_tokens', 'reasoningTokens'),
    };

    return Object.values(usage).some((v) => v !== null) ? usage : null;
}

export function formatUsage(usage) {
    if (!usage) {
        return null;
    }

    const parts = [];
    if (usage.prompt != null) {
        parts.push(`in: ${usage.prompt}`);
    }
    if (usage.completion != null) {
        parts.push(`out: ${usage.completion}`);
    }
    if (usage.cache_read != null) {
        parts.push(`cache read: ${usage.cache_read}`);
    }
    if (usage.cache_write != null) {
        parts.push(`cache write: ${usage.cache_write}`);
    }
    if (usage.reasoning != null) {
        parts.push(`reasoning: ${usage.reasoning}`);
    }

    return parts.join(' · ') || null;
}

function roleValue(role) {
    if (!role) {
        return null;
    }

    if (typeof role === 'string') {
        return role;
    }

    const props = role.properties ?? role;

    return props.value ?? props.name ?? role.name ?? null;
}

// Normalize one serialized message ({class, properties: {role, content,
// toolCalls, toolResults}}) to a thread row. Shared with ChatThread.vue.
export function normalizeMessage(message) {
    if (!message || typeof message !== 'object') {
        return null;
    }

    const props = message.properties ?? message;
    const content = message.content ?? props.content ?? null;
    const role = roleValue(message.role ?? props.role) ?? 'assistant';

    return {
        role,
        content: typeof content === 'string' ? content : (content ? JSON.stringify(content) : ''),
        tool_calls: props.toolCalls ?? [],
        tool_results: props.toolResults ?? [],
    };
}

// Tool calls requested inside a response (step text responses, agent
// responses): [{id, name, arguments}]. Prefers watcher-extracted
// content.response_tool_calls; falls back to serialized payload shapes
// (flat toArray or {class, properties} wrapped).
export function entryResponseToolCalls(entry) {
    const content = entry?.content ?? {};

    if (Array.isArray(content.response_tool_calls)) {
        return content.response_tool_calls;
    }

    const raw = content.payload?.response?.properties?.tool_calls
        ?? content.payload?.response?.properties?.toolCalls
        ?? content.payload?.response?.tool_calls
        ?? content.payload?.response?.toolCalls
        ?? null;

    if (!Array.isArray(raw)) {
        return [];
    }

    const out = [];

    for (const item of raw) {
        const props = item?.properties ?? item;

        if (!props || typeof props !== 'object') {
            continue;
        }

        out.push({
            id: props.id ?? null,
            name: props.name ?? null,
            arguments: props.arguments && typeof props.arguments === 'object' ? props.arguments : {},
        });
    }

    return out;
}

// Thread rows worth rendering: non-empty content or attached tool traffic.
export function isEmptyMessage(message) {
    if (!message || typeof message !== 'object') {
        return true;
    }

    if (typeof message.content === 'string' && message.content.trim() !== '') {
        return false;
    }

    const calls = message.tool_calls ?? message.toolCalls ?? [];
    const results = message.tool_results ?? message.toolResults ?? [];

    return calls.length === 0 && results.length === 0;
}

export function messageKey(message) {
    const calls = message.tool_calls ?? message.toolCalls ?? [];
    const results = message.tool_results ?? message.toolResults ?? [];
    const ids = [...calls, ...results].map((item) => item?.id ?? item?.name ?? '').join(',');

    return `${message.role ?? ''}|${message.content ?? ''}|${ids}`;
}
// Tool calls render as `name(args)`; everything else uses the backend summary
// plus step/duration when the summary lacks them. Never throws on legacy
// entries that miss the newer content fields.
export function historyLine(entry) {
    if (aiCategory(entry) === 'tool') {
        return toolCallLine(entry);
    }

    const content = entry?.content ?? {};
    let line = content.summary || content.name || 'ai';

    if (content.step != null && !/step \d/i.test(line)) {
        line += ` · step ${content.step}`;
    }

    if (content.duration != null) {
        line += ` · ${content.duration}ms`;
    }

    return line;
}

// Pair Ai.invokingTool with its Ai.toolInvoked / Ai.toolFailed finish by
// toolInvocationId. Unpaired starts are `running` (finish lost or still in
// flight); lone finishes stay usable on their own.
function hasArgs(entry) {
    const args = entry?.content?.payload?.arguments;

    return isPlainObject(args) && Object.keys(args).length > 0;
}

export function pairToolCalls(entries) {
    const groups = new Map();

    for (const entry of entries ?? []) {
        if (aiCategory(entry) !== 'tool') {
            continue;
        }

        const key = toolInvocationId(entry) ?? entry?.id ?? Math.random().toString(36);
        const name = entry?.content?.name ?? '';

        if (!groups.has(key)) {
            groups.set(key, { key, start: null, finish: null });
        }

        const group = groups.get(key);

        if (name === 'Ai.invokingTool' && !group.start) {
            group.start = entry;
        } else if ((name === 'Ai.toolInvoked' || name === 'Ai.toolFailed') && !group.finish) {
            group.finish = entry;
        } else if (!group.start) {
            group.start = entry;
        } else if (!group.finish) {
            group.finish = entry;
        }
    }

    return [...groups.values()].map((group) => {
        const main = group.finish ?? group.start;
        const args = group.start?.content?.payload?.arguments
            ?? group.finish?.content?.payload?.arguments
            ?? null;
        const failed = Boolean(group.finish?.content?.failed)
            || group.finish?.content?.name === 'Ai.toolFailed';
        const duration = group.finish?.content?.duration
            ?? group.finish?.content?.payload?.time
            ?? null;
        const result = group.finish ? resultText(group.finish) : null;
        const exception = group.finish?.content?.exception ?? null;
        const lineEntry = hasArgs(group.start) ? group.start : main;

        return {
            key: group.key,
            name: toolDisplayName(main) ?? 'tool',
            line: toolCallLine(lineEntry),
            args,
            result,
            exception,
            duration,
            failed,
            running: !group.finish,
            start: group.start,
            finish: group.finish,
            entry: main,
        };
    });
}

// Start → finish event pairs. A start and its finish collapse into ONE
// timeline item (request info often lives on the start, result on the finish).
// Streaming partials (streamingAgent) carry no response and disappear into
// their finish; unpaired starts render as running.
const PAIR_FAMILIES = {
    promptingAgent: ['agentPrompted', 'agentFailedEvent'],
    streamingAgent: ['agentStreamed', 'agentFailedEvent'],
    startingStep: ['stepCompleted', 'stepFailed'],
    invokingTool: ['toolInvoked', 'toolFailed'],
    generatingImage: ['imageGenerated'],
    generatingAudio: ['audioGenerated'],
    generatingTranscription: ['transcriptionGenerated'],
    generatingEmbeddings: ['embeddingsGenerated'],
    reranking: ['reranked'],
    creatingStore: ['storeCreated'],
    addingFileToStore: ['fileAddedToStore'],
    removingFileFromStore: ['fileRemovedFromStore'],
    storingFile: ['fileStored'],
};

function shortName(entry) {
    const name = entry?.content?.name ?? '';

    return name.startsWith('Ai.') ? name.slice(3) : name;
}

function pairKey(entry) {
    const short = shortName(entry);
    const content = entry?.content ?? {};

    if (short === 'startingStep' || short === 'stepCompleted' || short === 'stepFailed') {
        return `step:${content.step ?? '?'}`;
    }

    if (short === 'invokingTool' || short === 'toolInvoked' || short === 'toolFailed') {
        return `tool:${toolInvocationId(entry) ?? entry?.id}`;
    }

    return `family:${short}`;
}

// Build ONE chronological timeline from all entries of an invocation.
// Same-family start→finish pairs collapse into a single item combining both
// sides; everything else (failovers, approvals, deletes, lone events)
// renders as its own row. Covers all 36 event types without enumerating them:
// anything unknown is a single.
export function buildTimeline(entries) {
    const list = [...(entries ?? [])].sort((a, b) => (a?.sequence ?? 0) - (b?.sequence ?? 0));
    const items = [];
    const pending = new Map();

    const flush = (key) => {
        const item = pending.get(key);
        pending.delete(key);

        if (item) {
            items.push(item);
        }
    };

    for (const entry of list) {
        const short = shortName(entry);
        const finishes = PAIR_FAMILIES[short];

        if (finishes) {
            const key = pairKey(entry);
            // A second start of the same family means the previous one never
            // finished — flush it as its own (running) row first.
            flush(key);
            pending.set(key, singleItem(entry, true));
            continue;
        }

        let matched = false;
        for (const [key, open] of pending) {
            const openShort = shortName(open.start);
            if ((PAIR_FAMILIES[openShort] ?? []).includes(short) && pairKey(open.start) === pairKey(entry)) {
                pending.delete(key);
                items.push(mergePair(open.start, entry));
                matched = true;
                break;
            }
        }

        if (!matched) {
            items.push(singleItem(entry, false));
        }
    }

    for (const item of pending.values()) {
        items.push(item);
    }

    items.sort((a, b) => (a.start?.sequence ?? 0) - (b.start?.sequence ?? 0));

    return items;
}

function singleItem(entry, running) {
    return {
        key: entry?.id ?? Math.random().toString(36),
        category: aiCategory(entry),
        start: entry,
        finish: null,
        main: entry,
        entry: mergedEntry(entry, null),
        collapsed: false,
        running,
    };
}

function mergePair(start, finish) {
    const merged = mergedEntry(start, finish);

    return {
        key: finish?.id ?? start?.id ?? Math.random().toString(36),
        category: aiCategory(finish),
        start,
        finish,
        main: finish,
        entry: merged,
        collapsed: true,
        running: false,
    };
}

// Virtual entry combining both sides of a pair so all content readers
// (entryThread, entryResponseText, entryUsage, toolCallLine) work unchanged:
// request side (messages/prompt/arguments) from the start, result side
// (text/usage/result) from the finish.
function mergedEntry(start, finish) {
    if (!start) {
        return finish;
    }

    if (!finish) {
        return start;
    }

    const startContent = start.content ?? {};
    const finishContent = finish.content ?? {};
    const startThread = Array.isArray(startContent.thread) ? startContent.thread : [];
    const finishThread = Array.isArray(finishContent.thread) ? finishContent.thread : [];
    const startPayload = startContent.payload ?? {};
    const finishPayload = finishContent.payload ?? {};
    const startMessages = Array.isArray(startPayload.messages) ? startPayload.messages : [];
    const finishMessages = Array.isArray(finishPayload.messages) ? finishPayload.messages : [];

    return {
        ...finish,
        content: {
            ...finishContent,
            thread: [...startThread, ...finishThread],
            prompt_text: startContent.prompt_text ?? finishContent.prompt_text ?? null,
            response_text: finishContent.response_text ?? startContent.response_text ?? null,
            response_embeddings: finishContent.response_embeddings ?? startContent.response_embeddings ?? null,
            usage: finishContent.usage ?? startContent.usage ?? null,
            payload: {
                ...finishPayload,
                messages: [...startMessages, ...finishMessages],
            },
        },
    };
}

// Aggregate facts for an invocation history header. Entries may arrive in any
// order; the timeline itself is sorted by sequence ascending.
export function summarizeInvocation(entries) {
    const list = [...(entries ?? [])].sort((a, b) => (a?.sequence ?? 0) - (b?.sequence ?? 0));
    const counts = {};
    let model = null;
    let provider = null;
    let failed = false;

    for (const entry of list) {
        const category = aiCategory(entry);
        counts[category] = (counts[category] ?? 0) + 1;

        if (!model && entry?.content?.model) {
            model = entry.content.model;
        }

        if (!provider && entry?.content?.provider) {
            provider = entry.content.provider;
        }

        if (entry?.content?.failed) {
            failed = true;
        }
    }

    return {
        entries: list,
        total: list.length,
        counts,
        model,
        provider,
        failed,
        started: list.length ? list[0].created : null,
        finished: list.length ? list[list.length - 1].created : null,
    };
}
