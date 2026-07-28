#!/usr/bin/env node
/**
 * Host-app Speculum SPA build — compose core Speculum + related plugin panels.
 *
 * Related-plugin paths belong here (panels.json / SPECULUM_PANEL_ROOTS), not in
 * Speculum core resources/frontend. Copy this folder to `{APP}/resources/speculum`:
 *   npm run build
 *
 * Env overrides (optional):
 *   SPECULUM_APP_ROOT       Absolute path to the CakePHP app root
 *   SPECULUM_FRONTEND       Speculum Vite project (default: vendor/.../resources/frontend)
 *   SPECULUM_BUILD_OUTDIR   Output directory (default: {APP}/webroot/speculum)
 *   SPECULUM_PANEL_ROOTS    Comma-separated panel roots (overrides panels.json)
 */
import { existsSync, readFileSync } from 'fs';
import { spawnSync } from 'child_process';
import { dirname, isAbsolute, resolve } from 'path';
import { fileURLToPath } from 'url';

const here = dirname(fileURLToPath(import.meta.url));
const watch = process.argv.includes('--watch');

/**
 * @param {string} base
 * @param {string} pathValue
 * @returns {string}
 */
function resolvePath(base, pathValue) {
    if (isAbsolute(pathValue) || /^[a-zA-Z]:[\\/]/.test(pathValue)) {
        return pathValue;
    }

    return resolve(base, pathValue);
}

/**
 * @returns {string}
 */
function resolveAppRoot() {
    const fromEnv = (process.env.SPECULUM_APP_ROOT || '').trim();
    if (fromEnv !== '') {
        return resolvePath(process.cwd(), fromEnv);
    }

    return resolve(here, '../..');
}

/**
 * @param {string} appRoot
 * @param {string[]} requested Paths as configured (relative or absolute).
 * @returns {string[]}
 */
function resolveExistingPanelRoots(appRoot, requested) {
    const roots = [];
    for (const part of requested) {
        const absolute = resolvePath(appRoot, part);
        if (!existsSync(absolute)) {
            console.error('Panel root missing:', part);
            console.error('  resolved:', absolute);
            process.exit(1);
        }
        if (!roots.includes(absolute)) {
            roots.push(absolute);
        }
    }

    return roots;
}

/**
 * @param {string} appRoot
 * @returns {string[]}
 */
function resolvePanelRoots(appRoot) {
    const fromEnv = (process.env.SPECULUM_PANEL_ROOTS || '').trim();
    if (fromEnv !== '') {
        return resolveExistingPanelRoots(
            appRoot,
            fromEnv.split(',').map((part) => part.trim()).filter(Boolean),
        );
    }

    const panelsFile = resolve(here, 'panels.json');
    if (!existsSync(panelsFile)) {
        return [];
    }

    const parsed = JSON.parse(readFileSync(panelsFile, 'utf8'));
    if (!Array.isArray(parsed)) {
        throw new Error('panels.json must be a JSON array of paths');
    }

    return resolveExistingPanelRoots(
        appRoot,
        parsed.map((part) => String(part).trim()).filter(Boolean),
    );
}

const appRoot = resolveAppRoot();
const frontend = resolvePath(
    appRoot,
    (process.env.SPECULUM_FRONTEND || '').trim()
        || 'vendor/crustum/speculum/resources/frontend',
);
const outDir = resolvePath(
    appRoot,
    (process.env.SPECULUM_BUILD_OUTDIR || '').trim()
        || 'webroot/speculum',
);
const panelRoots = resolvePanelRoots(appRoot);

if (!existsSync(frontend)) {
    console.error('Speculum frontend missing:', frontend);
    process.exit(1);
}

const env = {
    ...process.env,
    SPECULUM_BUILD_OUTDIR: outDir,
    SPECULUM_PANEL_ROOTS: panelRoots.join(','),
};

console.log('Speculum host SPA build');
console.log('  app:     ', appRoot);
console.log('  frontend:', frontend);
console.log('  outDir:  ', outDir);
console.log('  panels:  ', panelRoots.length ? panelRoots.join('\n            ') : '(none)');
if (watch) {
    console.log('  mode:     watch');
}

const result = spawnSync(
    'npm',
    ['run', watch ? 'watch' : 'build'],
    {
        cwd: frontend,
        env,
        stdio: 'inherit',
        shell: true,
    },
);

process.exit(result.status ?? 1);
