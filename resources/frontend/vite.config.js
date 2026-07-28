import { existsSync } from 'fs';
import { isAbsolute, resolve } from 'path';
import { fileURLToPath } from 'url';
import vue from '@vitejs/plugin-vue';

const root = fileURLToPath(new URL('.', import.meta.url));

/**
 * Resolve a path that may be absolute (incl. Windows drive) or relative to the Vite root.
 *
 * @param {string} pathValue Path from env or config.
 * @returns {string}
 */
function resolveFromRoot(pathValue) {
    if (isAbsolute(pathValue) || /^[a-zA-Z]:[\\/]/.test(pathValue)) {
        return pathValue;
    }

    return resolve(root, pathValue);
}

/**
 * Host app sets SPECULUM_PANEL_ROOTS (comma-separated folders with register.js).
 * Plugin-local default builds have no panel roots (core panels live in Speculum).
 *
 * @returns {string[]}
 */
function resolvePanelRoots() {
    const roots = [];
    const env = process.env.SPECULUM_PANEL_ROOTS || '';
    env.split(',').forEach((part) => {
        const trimmed = part.trim();
        if (!trimmed) {
            return;
        }
        const absolute = resolveFromRoot(trimmed);
        if (existsSync(absolute) && !roots.includes(absolute)) {
            roots.push(absolute);
        }
    });

    return roots;
}

/**
 * Host app build: SPECULUM_BUILD_OUTDIR → e.g. {APP}/webroot/speculum.
 * Plugin-local default: plugins/Telescope/webroot/frontend.
 *
 * @returns {string}
 */
function resolveBuildOutDir() {
    const fromEnv = (process.env.SPECULUM_BUILD_OUTDIR || '').trim();
    if (fromEnv !== '') {
        return resolveFromRoot(fromEnv);
    }

    return resolve(root, '../../webroot/frontend');
}

/**
 * @param {string[]} panelRoots Absolute panel roots containing register.js.
 * @returns {import('vite').Plugin}
 */
function speculumExtensionsPlugin(panelRoots) {
    const virtualId = 'virtual:speculum-extensions';
    const resolvedVirtualId = '\0' + virtualId;

    return {
        name: 'speculum-extensions',
        resolveId(id) {
            if (id === virtualId) {
                return resolvedVirtualId;
            }

            return null;
        },
        load(id) {
            if (id !== resolvedVirtualId) {
                return null;
            }

            if (panelRoots.length === 0) {
                return 'export {};\n';
            }

            return panelRoots
                .map((panelRoot) => {
                    const registerPath = resolve(panelRoot, 'register.js').replace(/\\/g, '/');

                    return `import "${registerPath}";`;
                })
                .join('\n');
        },
    };
}

const panelRoots = resolvePanelRoots();
const buildOutDir = resolveBuildOutDir();

/** @type {import('vite').UserConfig} */
export default {
    plugins: [vue(), speculumExtensionsPlugin(panelRoots)],
    root,
    base: './',
    build: {
        outDir: buildOutDir,
        emptyOutDir: true,
        assetsDir: 'assets',
        manifest: 'manifest.json',
        chunkSizeWarningLimit: 1600,
        rollupOptions: {
            input: {
                app: resolve(root, 'js/app.js'),
                styles: resolve(root, 'sass/styles.scss'),
                'styles-dark': resolve(root, 'sass/styles-dark.scss'),
            },
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: 'chunks/[name]-[hash].js',
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name && assetInfo.name.endsWith('.css')) {
                        return '[name][extname]';
                    }

                    return 'assets/[name]-[hash][extname]';
                },
            },
        },
    },
    resolve: {
        alias: {
            '@': resolve(root, 'js'),
            vue: 'vue/dist/vue.esm-bundler.js',
            'speculum-extensions': resolve(root, 'js/extensions/registry.js'),
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                includePaths: [resolve(root, 'node_modules')],
                quietDeps: true,
                silenceDeprecations: [
                    'import',
                    'color-functions',
                    'global-builtin',
                    'legacy-js-api',
                    'if-function',
                ],
            },
        },
    },
};
