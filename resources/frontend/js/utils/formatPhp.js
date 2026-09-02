/**
 * Pretty-print a PHP code snippet for Speculum display.
 *
 * Lightweight brace/indent formatter — not a full PHP parser. Safe to call on
 * arbitrary strings; returns the original input when formatting is not useful.
 *
 * @param {string|null|undefined} code PHP source.
 * @param {{ indent?: string }} [options] Formatting options.
 * @returns {string}
 */
export function prettyPrintPhp(code, options = {}) {
    if (code == null) {
        return '';
    }

    const source = String(code).replace(/\r\n/g, '\n').trim();
    if (source === '') {
        return '';
    }

    const indentUnit = options.indent ?? '    ';
    const normalized = source
        .replace(/;\s*/g, ';\n')
        .replace(/\s*\{\s*/g, ' {\n')
        .replace(/\s*\}\s*/g, '\n}\n')
        .replace(/\n{3,}/g, '\n\n');

    const lines = normalized.split('\n');
    let depth = 0;
    const formatted = [];

    for (const rawLine of lines) {
        const line = rawLine.trim();
        if (line === '') {
            if (formatted.length === 0 || formatted[formatted.length - 1] !== '') {
                formatted.push('');
            }
            continue;
        }

        if (line.startsWith('}')) {
            depth = Math.max(depth - 1, 0);
        }

        formatted.push(indentUnit.repeat(depth) + line);

        if (line.endsWith('{')) {
            depth += 1;
        }
    }

    return formatted.join('\n').replace(/\n{3,}/g, '\n\n').trim();
}
