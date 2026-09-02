import { decodeBase64Json } from '../../utils/base64';
import { prettyPrintPhp } from '../../utils/formatPhp';

export const EXECUTE_TOOL_COMMAND = 'Crustum\\Ignis\\Command\\ExecuteToolCommand';

/**
 * Format Ignis ExecuteToolCommand arguments: tool FQCN + Base64 JSON payload.
 *
 * @param {object} entry Speculum command entry.
 * @returns {import('./registry').CommandFormatterResult|null}
 */
export function formatExecuteToolCommand(entry) {
    const args = entry?.content?.arguments;
    if (!Array.isArray(args) || args.length < 2) {
        return null;
    }

    const tool = args[0];
    const payload = decodeBase64Json(args[1]);
    if (payload == null || typeof payload !== 'object' || Array.isArray(payload)) {
        return null;
    }

    const sections = [
        {
            kind: 'json',
            label: 'Tool',
            data: {
                tool,
                ...Object.fromEntries(
                    Object.entries(payload).filter(([key]) => key !== 'code'),
                ),
            },
        },
    ];

    if (typeof payload.code === 'string' && payload.code !== '') {
        sections.push({
            kind: 'php',
            label: 'Code',
            data: prettyPrintPhp(payload.code),
            copy: prettyPrintPhp(payload.code),
        });
    } else {
        sections[0].data = { tool, ...payload };
    }

    return {
        kind: 'sections',
        sections,
        copy: {
            tool,
            ...payload,
            ...(typeof payload.code === 'string' ? { code: prettyPrintPhp(payload.code) } : {}),
        },
    };
}

export default {
    id: 'ignis-execute-tool',
    label: 'Tinker',
    commands: EXECUTE_TOOL_COMMAND,
    format: formatExecuteToolCommand,
};
