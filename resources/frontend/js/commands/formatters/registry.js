/**
 * In-Speculum command preview formatter registry.
 *
 * Register formatters that match a command FQCN. The commands preview screen
 * adds a tab per matching formatter — new formatters only need registration.
 */

/**
 * @typedef {{
 *   id: string,
 *   label: string,
 *   commands?: string|string[],
 *   match?: (entry: object) => boolean,
 *   format: (entry: object) => CommandFormatterResult|null|undefined,
 * }} CommandFormatterDefinition
 *
 * @typedef {{
 *   kind: 'json'|'text'|'php'|'sections',
 *   data?: unknown,
 *   copy?: unknown,
 *   sections?: Array<{ kind: 'json'|'text'|'php', data: unknown, copy?: unknown, label?: string }>,
 * }} CommandFormatterResult
 */

/** @type {CommandFormatterDefinition[]} */
const formatters = [];

/**
 * Register (or replace) a command preview formatter.
 *
 * @param {CommandFormatterDefinition} definition Formatter definition.
 * @returns {void}
 */
export function registerCommandFormatter(definition) {
    if (!definition || !definition.id || typeof definition.format !== 'function') {
        return;
    }

    const index = formatters.findIndex((item) => item.id === definition.id);
    const normalized = {
        id: definition.id,
        label: definition.label || definition.id,
        commands: definition.commands,
        match: definition.match,
        format: definition.format,
    };

    if (index >= 0) {
        formatters[index] = normalized;
        return;
    }

    formatters.push(normalized);
}

/**
 * Clear registered formatters (tests).
 *
 * @returns {void}
 */
export function resetCommandFormatters() {
    formatters.length = 0;
}

/**
 * @returns {CommandFormatterDefinition[]}
 */
export function getCommandFormatters() {
    return [...formatters];
}

/**
 * Whether a formatter applies to the given command entry.
 *
 * @param {CommandFormatterDefinition} formatter Formatter definition.
 * @param {object} entry Speculum command entry.
 * @returns {boolean}
 */
export function formatterMatches(formatter, entry) {
    if (typeof formatter.match === 'function') {
        return formatter.match(entry) === true;
    }

    const command = entry?.content?.command;
    if (!command || formatter.commands == null) {
        return false;
    }

    const commands = Array.isArray(formatter.commands)
        ? formatter.commands
        : [formatter.commands];

    return commands.includes(command);
}

/**
 * Resolve matching formatter tabs for a command entry (skips null format results).
 *
 * @param {object} entry Speculum command entry.
 * @returns {Array<{ id: string, label: string, result: CommandFormatterResult }>}
 */
export function resolveCommandFormatterTabs(entry) {
    const tabs = [];

    for (const formatter of formatters) {
        if (!formatterMatches(formatter, entry)) {
            continue;
        }

        let result;
        try {
            result = formatter.format(entry);
        } catch (_error) {
            continue;
        }

        if (!result || !result.kind) {
            continue;
        }

        tabs.push({
            id: formatter.id,
            label: formatter.label,
            result,
        });
    }

    return tabs;
}
