import { beforeEach, describe, expect, it } from 'vitest';
import {
    registerCommandFormatter,
    resetCommandFormatters,
    resolveCommandFormatterTabs,
} from '../commands/formatters/registry';
import { formatExecuteToolCommand, EXECUTE_TOOL_COMMAND } from '../commands/formatters/executeToolCommand';

describe('command formatters', () => {
    beforeEach(() => {
        resetCommandFormatters();
    });

    it('resolves tabs only for matching command class names', () => {
        registerCommandFormatter({
            id: 'demo',
            label: 'Demo',
            commands: 'App\\Command\\DemoCommand',
            format: () => ({ kind: 'text', data: 'ok' }),
        });

        const matched = resolveCommandFormatterTabs({
            content: { command: 'App\\Command\\DemoCommand' },
        });
        const missed = resolveCommandFormatterTabs({
            content: { command: 'App\\Command\\OtherCommand' },
        });

        expect(matched).toHaveLength(1);
        expect(matched[0].label).toBe('Demo');
        expect(missed).toHaveLength(0);
    });

    it('formats ExecuteToolCommand payload with decoded PHP', () => {
        const code = '$d = 1; if ($d) { return $d; }';
        const encoded = btoa(JSON.stringify({ code, timeout: 45 }));
        const entry = {
            content: {
                command: EXECUTE_TOOL_COMMAND,
                arguments: ['Crustum\\Ignis\\Mcp\\Tools\\Tinker', encoded],
            },
        };

        registerCommandFormatter({
            id: 'ignis-execute-tool',
            label: 'Tinker',
            commands: EXECUTE_TOOL_COMMAND,
            format: formatExecuteToolCommand,
        });

        const tabs = resolveCommandFormatterTabs(entry);
        expect(tabs).toHaveLength(1);
        expect(tabs[0].result.kind).toBe('sections');
        expect(tabs[0].result.sections[0].data).toEqual({
            tool: 'Crustum\\Ignis\\Mcp\\Tools\\Tinker',
            timeout: 45,
        });
        expect(tabs[0].result.sections[1].kind).toBe('php');
        expect(tabs[0].result.sections[1].data).toContain('return $d;');
    });
});
