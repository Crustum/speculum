import { computed, onMounted, ref } from 'vue';
import api from '@/utils/api';
import {
    buildTimeline,
    entryEmbeddings,
    entryException,
    entryResponseText,
    entryResponseToolCalls,
    entryThread,
    entryUsage,
    formatEmbeddings,
    formatUsage,
    isEmptyMessage,
    messageKey,
    summarizeInvocation,
} from '@/utils/aiTool';

// Loads ALL events of one invocation (the API clamps `take`, so pages walk
// back with the `before` sequence cursor until a short page) and derives the
// summary, per-category counts and the deduplicated timeline. The timeline
// renders oldest-first only after the full history is loaded.
export function useInvocationHistory(invocationId) {
    const ready = ref(false);
    const loadError = ref(false);
    const loadedCount = ref(0);
    const rawEntries = ref([]);

    const summary = computed(() => summarizeInvocation(rawEntries.value));
    const timeline = computed(() => buildTimeline(summary.value.entries));

    const countLine = computed(() => {
        const counts = summary.value.counts;

        return Object.keys(counts)
            .sort()
            .map((key) => `${key}: ${counts[key]}`)
            .join(' · ');
    });

    // Agent steps repeat the full accumulated conversation, so only messages
    // not shown by earlier timeline items render. Empty rows never render.
    const timelineItems = computed(() => {
        const seen = new Set();

        return timeline.value.map((item) => {
            const fresh = [];

            for (const message of entryThread(item.entry)) {
                if (isEmptyMessage(message)) {
                    continue;
                }

                const key = messageKey(message);
                if (seen.has(key)) {
                    continue;
                }

                seen.add(key);
                fresh.push(message);
            }

            const toolCalls = entryResponseToolCalls(item.entry);
            const usageLine = formatUsage(entryUsage(item.entry));
            const embeddingsLine = formatEmbeddings(entryEmbeddings(item.entry));

            let responseText = entryResponseText(item.entry);
            if (typeof responseText !== 'string' || responseText.trim() === '') {
                responseText = null;
            } else if (seen.has(`assistant|${responseText}`)) {
                responseText = null;
            } else {
                seen.add(`assistant|${responseText}`);
            }

            const hasBody = fresh.length > 0
                || responseText !== null
                || toolCalls.length > 0
                || usageLine !== null
                || embeddingsLine !== null
                || entryException(item.main) !== null
                || item.category === 'tool';

            return {
                ...item,
                newMessages: fresh,
                responseText,
                responseToolCalls: toolCalls,
                usageLine,
                embeddingsLine,
                hasBody,
            };
        });
    });

    function load() {
        ready.value = false;
        loadError.value = false;
        rawEntries.value = [];
        loadedCount.value = 0;

        const tag = encodeURIComponent(`invocation:${invocationId.value}`);
        const seenIds = new Set();
        let before = '';
        let pages = 0;

        const loadPage = () => {
            pages += 1;

            api
                .post(`/ai?tag=${tag}&take=100${before ? `&before=${before}` : ''}`)
                .then((response) => {
                    const list = response.data.entries || [];

                    for (const entry of list) {
                        if (!seenIds.has(entry.id)) {
                            seenIds.add(entry.id);
                            rawEntries.value.push(entry);
                        }
                    }

                    loadedCount.value = rawEntries.value.length;

                    if (list.length >= 100 && pages < 100) {
                        before = String(list[list.length - 1].sequence ?? '');
                        loadPage();

                        return;
                    }

                    ready.value = true;
                })
                .catch(() => {
                    loadError.value = true;
                    ready.value = true;
                });
        };

        loadPage();
    }

    onMounted(load);

    return {
        ready,
        loadError,
        loadedCount,
        summary,
        countLine,
        timelineItems,
        reload: load,
    };
}
