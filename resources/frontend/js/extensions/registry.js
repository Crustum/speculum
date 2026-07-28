/**
 * Speculum SPA panel extension registry (host / sibling plugins).
 */

/** @type {import('vue-router').RouteRecordRaw[]} */
const extensionRoutes = [];

/** @type {Array<{ key: string, label: string, watcher: string, to: string, group: number }>} */
const extensionNavItems = [];

/** @type {Array<{ type: string, title: string, component: object, match: (item: object) => boolean }>} */
const extensionRelatedDefinitions = [];

/**
 * Register a panel owned by an external plugin or the host app.
 *
 * @param {{
 *   key: string,
 *   label: string,
 *   watcher?: string,
 *   group?: number,
 *   routes: import('vue-router').RouteRecordRaw[],
 *   related?: { type: string, title: string, component: object, match: (item: object) => boolean },
 * }} panel Panel definition.
 * @returns {void}
 */
export function registerPanel(panel) {
    if (!panel || !panel.key || !Array.isArray(panel.routes)) {
        return;
    }

    const key = panel.key;
    const watcher = panel.watcher || key;
    const group = panel.group ?? 2;
    const to = panel.routes.find((route) => !String(route.path).includes(':'))?.path || `/${key}`;

    extensionNavItems.push({
        key,
        label: panel.label || key,
        watcher,
        to,
        group,
    });

    panel.routes.forEach((route) => {
        extensionRoutes.push(route);
    });

    if (panel.related && panel.related.component && typeof panel.related.match === 'function') {
        extensionRelatedDefinitions.push({
            type: panel.related.type || key,
            title: panel.related.title || panel.label || key,
            component: panel.related.component,
            match: panel.related.match,
        });
    }
}

/**
 * @returns {import('vue-router').RouteRecordRaw[]}
 */
export function getExtensionRoutes() {
    return extensionRoutes;
}

/**
 * @returns {Array<{ key: string, label: string, watcher: string, to: string, group: number }>}
 */
export function getExtensionNavItems() {
    return extensionNavItems;
}

/**
 * @returns {Array<{ type: string, title: string, component: object, match: (item: object) => boolean }>}
 */
export function getExtensionRelatedDefinitions() {
    return extensionRelatedDefinitions;
}
