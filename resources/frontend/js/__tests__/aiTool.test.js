import { describe, expect, it } from 'vitest';
import {
    buildTimeline,
    entryAgentClass,
    entryArguments,
    entryEmbeddings,
    entryException,
    entryResponseText,
    entryResponseToolCalls,
    entryThread,
    entryToolClass,
    entryUsage,
    formatEmbeddings,
    formatUsage,
    formatDateTime,
    historyLine,
    isEmptyMessage,
    innerToolClass,
    pairToolCalls,
    resultText,
    shortClassName,
    summarizeInvocation,
    summarizeArgs,
    summarizeValue,
    toolCallLine,
    toolDisplayName,
    toolInvocationId,
    toolWrapperName,
} from '../utils/aiTool';

const wrappedEntry = {
    content: {
        name: 'Ai.invokingTool',
        tool: 'AskQuestion',
        tool_name: 'ask_user',
        tool_class: 'AskQuestion',
        tool_wrapper: 'EventedTool',
        tool_invocation_id: 'tool-1',
        payload: {
            tool: {
                class: 'EventedTool',
                properties: { inner: { class: 'AskQuestion', properties: {} } },
            },
            arguments: {
                options: [{ label: 'a', description: 'b' }],
                multiple: true,
            },
            toolInvocationId: 'tool-1',
        },
    },
};

const legacyEntry = {
    content: {
        name: 'Ai.toolInvoked',
        tool: 'EventedTool',
        summary: 'EventedTool · toolInvoked',
        payload: {
            tool: {
                class: 'EventedTool',
                properties: { inner: { class: 'AskQuestion', properties: {} } },
            },
            arguments: { question: 'Proceed?' },
            result: 'The user did not answer…',
        },
    },
};

const approvalEntry = {
    content: {
        name: 'Ai.toolApprovalResolved',
        category: 'tool',
        summary: 'tool · toolApprovalResolved',
        payload: {
            agent: { class: 'App\\Ai\\SlowQueryAgent', properties: {} },
            toolResults: [
                {
                    class: 'Crustum\\Ai\\Responses\\Data\\ToolResult',
                    properties: {
                        id: 'z2qprqhzy',
                        name: 'apply-indexes',
                        arguments: { indexes: '[{"table": "posts"}]' },
                        result: 'Applied 1 index(es)',
                        result_id: 'z2qprqhzy',
                    },
                },
            ],
        },
    },
};

describe('aiTool', () => {
    it('parses approval payloads from tool results', () => {        expect(toolDisplayName(approvalEntry)).toBe('apply-indexes');
        expect(toolInvocationId(approvalEntry)).toBe('z2qprqhzy');
        expect(entryArguments(approvalEntry)).toEqual({ indexes: '[{"table": "posts"}]' });
        expect(toolCallLine(approvalEntry)).toBe('apply-indexes(indexes: [{"table": "posts"}])');
        expect(resultText(approvalEntry)).toBe('Applied 1 index(es)');
    });

    it('reads embeddings prompts and response shapes', () => {
        const start = {
            content: {
                name: 'Ai.generatingEmbeddings',
                category: 'generation',
                payload: {
                    prompt: {
                        class: 'Crustum\\Ai\\Prompts\\EmbeddingsPrompt',
                        properties: {
                            inputs: ['i want add table field into page'],
                            dimensions: 2048,
                        },
                    },
                },
            },
        };
        const finish = {
            content: {
                name: 'Ai.embeddingsGenerated',
                category: 'generation',
                payload: {
                    response: {
                        class: 'Crustum\\Ai\\Responses\\EmbeddingsResponse',
                        properties: { embeddings: [[0.10947755, 0.034772266]] },
                    },
                },
            },
        };

        const thread = entryThread(start);
        expect(thread).toHaveLength(1);
        expect(thread[0].role).toBe('prompt');
        expect(thread[0].content).toBe('i want add table field into page');

        expect(entryEmbeddings(finish)).toEqual({ count: 1, dimensions: 2 });
        expect(formatEmbeddings(entryEmbeddings(finish))).toBe('1 vector × 2 dims');
        expect(formatEmbeddings(entryEmbeddings(start))).toBeNull();
    });

    it('reads failure lines from stored and legacy exceptions', () => {
        expect(entryException({
            content: {
                name: 'Ai.stepFailed',
                exception: {
                    class: 'Crustum\\Ai\\Exception\\RateLimitedException',
                    message: 'Application rate limited by AI provider [groq].',
                },
            },
        })).toBe('Crustum\\Ai\\Exception\\RateLimitedException: Application rate limited by AI provider [groq].');
        expect(entryException({
            content: {
                name: 'Ai.agentFailedEvent',
                payload: {
                    exception: {
                        class: 'RuntimeException',
                        properties: { class: 'RuntimeException', message: 'boom' },
                    },
                },
            },
        })).toBe('RuntimeException: boom');
        expect(entryException({ content: { name: 'Ai.stepCompleted' } })).toBeNull();
    });

    it('prefers resolved backend fields for display name and wrapper', () => {
        expect(toolDisplayName(wrappedEntry)).toBe('ask_user');
        expect(innerToolClass(wrappedEntry)).toBe('AskQuestion');
        expect(toolWrapperName(wrappedEntry)).toBe('EventedTool');
        expect(toolInvocationId(wrappedEntry)).toBe('tool-1');
    });

    it('falls back to payload unwrap for legacy entries', () => {
        expect(toolDisplayName(legacyEntry)).toBe('AskQuestion');
        expect(innerToolClass(legacyEntry)).toBe('AskQuestion');
        expect(toolWrapperName(legacyEntry)).toBe('EventedTool');
    });

    it('builds a Symfony-style tool call line', () => {
        expect(toolCallLine(legacyEntry)).toBe('AskQuestion(question: Proceed?)');
        expect(toolCallLine(wrappedEntry)).toBe('ask_user(options: [1 item], multiple: true)');
    });

    it('extracts string results as text', () => {
        expect(resultText(legacyEntry)).toBe('The user did not answer…');
        expect(resultText(wrappedEntry)).toBeNull();
    });

    it('summarizes values for history rows', () => {
        expect(summarizeValue(null)).toBe('—');
        expect(summarizeValue(42)).toBe('42');
        expect(summarizeValue([1, 2, 3])).toBe('[1, 2, 3]');
        expect(summarizeValue([])).toBe('[]');
        expect(summarizeValue({ a: 1, b: 2, c: 3, d: 4 })).toBe('{4 keys}');
        expect(summarizeValue('  a\n  b  ')).toBe('a b');
    });

    it('builds history lines for any category', () => {
        expect(historyLine(legacyEntry)).toBe('AskQuestion(question: Proceed?)');
        expect(historyLine({
            content: {
                name: 'Ai.stepCompleted',
                category: 'agent',
                summary: 'stepCompleted · step 0',
                duration: 3877,
            },
        })).toBe('stepCompleted · step 0 · 3877ms');
        expect(historyLine({
            content: {
                name: 'Ai.providerFailedOver',
                category: 'failover',
                summary: 'openai → groq',
            },
        })).toBe('openai → groq');
    });

    it('pairs invoking and finish tool events', () => {
        const invoking = {
            id: 'start-1',
            sequence: 1,
            content: {
                name: 'Ai.invokingTool',
                category: 'tool',
                tool_name: 'ask_user',
                tool_invocation_id: 'tool-1',
                payload: { arguments: { question: 'Proceed?' } },
            },
        };
        const invoked = {
            id: 'finish-1',
            sequence: 2,
            content: {
                name: 'Ai.toolInvoked',
                category: 'tool',
                tool_name: 'ask_user',
                tool_invocation_id: 'tool-1',
                duration: 57884,
                failed: false,
                payload: { result: 'The user did not answer…' },
            },
        };

        const pairs = pairToolCalls([invoked, invoking]);

        expect(pairs).toHaveLength(1);
        expect(pairs[0].name).toBe('ask_user');
        expect(pairs[0].line).toBe('ask_user(question: Proceed?)');
        expect(pairs[0].result).toBe('The user did not answer…');
        expect(pairs[0].duration).toBe(57884);
        expect(pairs[0].running).toBe(false);
    });

    it('marks unpaired tool starts as running', () => {
        const pairs = pairToolCalls([{
            id: 'start-9',
            sequence: 9,
            content: {
                name: 'Ai.invokingTool',
                category: 'tool',
                tool_invocation_id: 'tool-9',
                payload: { arguments: {} },
            },
        }]);

        expect(pairs).toHaveLength(1);
        expect(pairs[0].running).toBe(true);
    });

    it('summarizes an invocation', () => {        const summary = summarizeInvocation([
            { id: 'b', sequence: 2, created: 't2', content: { category: 'tool', model: 'm', provider: 'p' } },
            { id: 'a', sequence: 1, created: 't1', content: { category: 'agent', failed: true } },
        ]);

        expect(summary.total).toBe(2);
        expect(summary.counts).toEqual({ agent: 1, tool: 1 });
        expect(summary.model).toBe('m');
        expect(summary.failed).toBe(true);
        expect(summary.entries[0].id).toBe('a');
    });

    it('reads thread from watcher-extracted content first', () => {
        const thread = entryThread({
            content: {
                thread: [{ role: 'user', content: 'Show me slow queries', tool_calls: [], tool_results: [] }],
                payload: { messages: [{ class: 'X', properties: { role: 'user', content: 'stale' } }] },
            },
        });

        expect(thread).toHaveLength(1);
        expect(thread[0].content).toBe('Show me slow queries');
    });

    it('falls back to serialized payload messages', () => {
        const thread = entryThread({
            content: {
                payload: {
                    messages: [{
                        class: 'Crustum\\Ai\\Messages\\Message',
                        properties: {
                            role: {
                                class: 'Crustum\\Ai\\Messages\\MessageRole',
                                properties: { name: 'User', value: 'user' },
                            },
                            content: 'Show me the slow queries dashboard and help me optimize them',
                        },
                    }],
                },
            },
        });

        expect(thread).toHaveLength(1);
        expect(thread[0].role).toBe('user');
        expect(thread[0].content).toBe('Show me the slow queries dashboard and help me optimize them');
    });

    it('reads prompt text from serialized AgentPrompt', () => {
        const thread = entryThread({
            content: {
                payload: {
                    prompt: {
                        class: 'Crustum\\Ai\\Prompts\\AgentPrompt',
                        properties: { model: 'qwen3.8-flash', prompt: 'compact' },
                    },
                },
            },
        });

        expect(thread).toHaveLength(1);
        expect(thread[0].role).toBe('prompt');
        expect(thread[0].content).toBe('compact');
    });

    it('reads response text and usage with legacy fallbacks', () => {
        expect(entryResponseText({ content: { response_text: 'hi' } })).toBe('hi');
        expect(entryResponseText({
            content: { payload: { response: { properties: { text: 'legacy hi' } } } },
        })).toBe('legacy hi');
        expect(entryResponseText({ content: { payload: {} } })).toBeNull();

        expect(entryUsage({ content: { usage: { prompt: 1, completion: 2 } } })).toEqual({ prompt: 1, completion: 2 });
        expect(entryUsage({
            content: { payload: { response: { properties: { usage: { prompt_tokens: 10, completion_tokens: 5 } } } } },
        })).toEqual({ prompt: 10, completion: 5, cache_read: null, cache_write: null, reasoning: null });
        expect(formatUsage({ prompt: 10, completion: 5, cache_read: null, cache_write: null, reasoning: null }))
            .toBe('in: 10 · out: 5');
        expect(formatUsage(null)).toBeNull();
    });

    it('collapses start/finish pairs into one timeline item', () => {
        const start = {
            id: 's0', sequence: 1, created: 't1',
            content: {
                name: 'Ai.startingStep', category: 'agent', step: 0,
                thread: [{ role: 'user', content: 'hi', tool_calls: [], tool_results: [] }],
            },
        };
        const finish = {
            id: 'f0', sequence: 2, created: 't2',
            content: {
                name: 'Ai.stepCompleted', category: 'agent', step: 0, duration: 100,
                response_text: 'hello', usage: { prompt: 1, completion: 2 },
            },
        };
        const lone = {
            id: 'x', sequence: 3, created: 't3',
            content: { name: 'Ai.providerFailedOver', category: 'failover', summary: 'a → b' },
        };

        const items = buildTimeline([finish, lone, start]);

        expect(items).toHaveLength(2);
        expect(items[0].collapsed).toBe(true);
        expect(items[0].category).toBe('agent');
        expect(entryThread(items[0].entry)).toHaveLength(1);
        expect(entryResponseText(items[0].entry)).toBe('hello');
        expect(items[1].collapsed).toBe(false);
        expect(historyLine(items[1].entry)).toBe('a → b');
    });

    it('keeps unpaired starts as running rows', () => {
        const items = buildTimeline([{
            id: 's', sequence: 1, created: 't',
            content: { name: 'Ai.streamingAgent', category: 'agent' },
        }]);

        expect(items).toHaveLength(1);
        expect(items[0].running).toBe(true);
    });

    it('summarizes argument objects', () => {
        expect(summarizeArgs({ table: 'Articles' })).toBe('table: Articles');
        expect(summarizeArgs(null)).toBe('');
        expect(summarizeArgs([1])).toBe('');
    });

    it('reads entry arguments and formats absolute time', () => {        expect(entryArguments({ content: { payload: { arguments: { query: 'x' } } } })).toEqual({ query: 'x' });
        expect(entryArguments({ content: {} })).toEqual({});
        expect(formatDateTime('2026-09-04T16:36:00+00:00')).toMatch(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/);
        expect(formatDateTime(null)).toBe('—');
        expect(formatDateTime('garbage')).toBe('garbage');
    });

    it('reads agent class with payload fallback', () => {
        expect(entryAgentClass({ content: { agent_class: 'App\\Agent\\X' } })).toBe('App\\Agent\\X');
        expect(entryAgentClass({
            content: { payload: { agent: { class: 'App\\Agent\\Y', properties: {} } } },
        })).toBe('App\\Agent\\Y');
        expect(entryAgentClass({ content: {} })).toBeNull();
    });

    it('shortens class names and reads full tool class', () => {
        expect(shortClassName('Crustum\\Panifex\\Tool\\ReadFile')).toBe('ReadFile');
        expect(shortClassName('ReadFile')).toBe('ReadFile');
        expect(entryToolClass({
            content: { payload: { tool: { class: 'EventedTool', properties: { inner: { class: 'Crustum\\Panifex\\Tool\\ReadFile' } } } } },
        })).toBe('Crustum\\Panifex\\Tool\\ReadFile');
        expect(entryToolClass({ content: { tool_class: 'ReadFile', tool_wrapper: 'EventedTool' } })).toBe('ReadFile');
    });

    it('detects empty messages and reads response tool calls', () => {
        expect(isEmptyMessage(null)).toBe(true);
        expect(isEmptyMessage({ role: 'assistant', content: '  ' })).toBe(true);
        expect(isEmptyMessage({ role: 'user', content: 'hi' })).toBe(false);
        expect(entryResponseToolCalls({
            content: { response_tool_calls: [{ id: '1', name: 't', arguments: {} }] },
        })).toHaveLength(1);
        expect(entryResponseToolCalls({
            content: { payload: { response: { properties: { tool_calls: [{ id: '2', name: 'u', arguments: {} }] } } } },
        })).toHaveLength(1);
        expect(entryResponseToolCalls({ content: {} })).toEqual([]);
    });
});
