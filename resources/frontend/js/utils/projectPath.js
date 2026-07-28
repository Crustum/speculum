/**
 * localStorage key for browser-local project ROOT (IDE remap).
 */
export const LOCAL_ROOT_STORAGE_KEY = 'speculumLocalProjectRoot';

const EDITOR_TEMPLATES = {
    atom: 'atom://core/open/file?filename={file}&line={line}',
    cursor: 'cursor://file/{file}:{line}',
    emacs: 'emacs://open?url=file://{file}&line={line}',
    macvim: 'mvim://open/?url=file://{file}&line={line}',
    phpstorm: 'phpstorm://open?file={file}&line={line}',
    sublime: 'subl://open?url=file://{file}&line={line}',
    textmate: 'txmt://open?url=file://{file}&line={line}',
    vscode: 'vscode://file/{file}:{line}',
    vscodium: 'vscodium://file/{file}:{line}',
};

/**
 * Server ROOT from SPA bootstrap.
 *
 * @returns {string}
 */
export function serverRoot() {
    return typeof window !== 'undefined' && window.Speculum?.root
        ? String(window.Speculum.root)
        : '';
}

/**
 * Local ROOT from localStorage (browser override).
 *
 * @returns {string}
 */
export function localRoot() {
    if (typeof window === 'undefined' || !window.localStorage) {
        return '';
    }

    return String(window.localStorage.getItem(LOCAL_ROOT_STORAGE_KEY) || '').trim();
}

/**
 * Persist local ROOT override (empty clears).
 *
 * @param {string} root Path.
 * @returns {void}
 */
export function setLocalRoot(root) {
    if (typeof window === 'undefined' || !window.localStorage) {
        return;
    }

    const trimmed = String(root || '').trim();
    if (trimmed === '') {
        window.localStorage.removeItem(LOCAL_ROOT_STORAGE_KEY);
        return;
    }

    window.localStorage.setItem(LOCAL_ROOT_STORAGE_KEY, normalizePath(trimmed));
}

/**
 * Effective project ROOT: localStorage override, else server ROOT.
 *
 * @returns {string}
 */
export function projectRoot() {
    return localRoot() || serverRoot();
}

/**
 * Normalize path separators to `/` and trim trailing slashes.
 *
 * @param {string} path Path.
 * @returns {string}
 */
export function normalizePath(path) {
    return String(path)
        .replace(/\\/g, '/')
        .replace(/\/+$/, '');
}

/**
 * Strip a root prefix from a path when it matches.
 *
 * @param {string} file Normalized file path.
 * @param {string} root Normalized root.
 * @returns {string|null} Relative path or null if not under root.
 */
function stripRoot(file, root) {
    if (!root) {
        return null;
    }

    const fileLower = file.toLowerCase();
    const rootLower = root.toLowerCase();

    if (fileLower === rootLower) {
        return '';
    }

    const prefix = rootLower + '/';
    if (fileLower.startsWith(prefix)) {
        return file.slice(root.length + 1);
    }

    return null;
}

/**
 * Display path relative to server ROOT first, then local ROOT.
 *
 * @param {string|null|undefined} file Absolute or relative path.
 * @returns {string}
 */
export function relativeToRoot(file) {
    if (file == null || file === '') {
        return '';
    }

    const normalizedFile = normalizePath(file);
    const fromServer = stripRoot(normalizedFile, normalizePath(serverRoot()));
    if (fromServer !== null) {
        return fromServer;
    }

    const fromLocal = stripRoot(normalizedFile, normalizePath(localRoot()));
    if (fromLocal !== null) {
        return fromLocal;
    }

    const fromEffective = stripRoot(normalizedFile, normalizePath(projectRoot()));
    if (fromEffective !== null) {
        return fromEffective;
    }

    return normalizedFile;
}

/**
 * Map a stored (usually server-absolute) path to a local absolute path for the IDE.
 *
 * @param {string|null|undefined} file Path.
 * @returns {string}
 */
export function resolveLocalFile(file) {
    if (file == null || file === '') {
        return '';
    }

    const normalizedFile = normalizePath(file);
    const local = normalizePath(localRoot());
    if (!local) {
        return normalizedFile;
    }

    const relative = relativeToRoot(normalizedFile);
    if (relative === normalizedFile) {
        return normalizedFile;
    }

    if (relative === '') {
        return local;
    }

    return `${local}/${relative}`;
}

/**
 * Build an IDE URL (Cake Debugger template parity).
 *
 * @param {string} file Absolute path for the editor.
 * @param {number|string} line Line number.
 * @returns {string|null}
 */
export function buildEditorUrl(file, line = 1) {
    if (!file) {
        return null;
    }

    const editor =
        typeof window !== 'undefined' && window.Speculum?.editor
            ? String(window.Speculum.editor)
            : 'phpstorm';
    const template = EDITOR_TEMPLATES[editor] || EDITOR_TEMPLATES.phpstorm;
    const lineNumber = Math.max(1, Number(line) || 1);

    return template.replaceAll('{file}', file).replaceAll('{line}', String(lineNumber));
}

/**
 * Prefer remapped local IDE URL when local ROOT is set; else server `editor_url`.
 *
 * @param {string|null|undefined} file Stored file path.
 * @param {number|string|null|undefined} line Line.
 * @param {string|null|undefined} serverEditorUrl API editor_url.
 * @returns {string|undefined}
 */
export function editorHref(file, line, serverEditorUrl) {
    if (localRoot() && file) {
        const mapped = resolveLocalFile(file);
        const url = buildEditorUrl(mapped, line ?? 1);
        if (url) {
            return url;
        }
    }

    return serverEditorUrl || undefined;
}

/**
 * Format `file:line` for UI (relative display).
 *
 * @param {string|null|undefined} file Path.
 * @param {number|string|null|undefined} line Line number.
 * @returns {string|undefined}
 */
export function formatFileLocation(file, line) {
    if (!file) {
        return undefined;
    }

    const display = relativeToRoot(file);

    return line != null && line !== '' ? `${display}:${line}` : display;
}
