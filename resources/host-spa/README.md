# Speculum host SPA build

Copy this folder into your CakePHP application as `resources/speculum` when you want the Speculum dashboard assets built **into the host app** (typically `webroot/speculum`) instead of relying on the plugin’s own `webroot/frontend` symlink. Use the same layout when you ship **custom Speculum panels** from other Composer packages or from the application itself: list each panel root here, rebuild, and point `Speculum.assets.path` at the output folder.

## Setup

Copy `resources/host-spa` from the Speculum package (or from `vendor/crustum/speculum/resources/host-spa` after Composer install) to `{APP}/resources/speculum`. Rename `panels.json.example` to `panels.json` and list every panel package path relative to the application root (or absolute). Each path must contain a `register.js` that calls Speculum’s `registerPanel` helper.

Install Speculum’s frontend dependencies once (the host script runs Vite inside the package):

```bash
cd vendor/crustum/speculum/resources/frontend
npm install
```

Then from the host copy:

```bash
cd resources/speculum
npm run build
```

Optional watch mode: `npm run watch`.

Configure Speculum to serve the built files from the host webroot (defaults match this script):

```php
'assets' => [
    'path' => env('SPECULUM_ASSETS_PATH', 'speculum'),
],
```

The dashboard then loads `/speculum/app.js` (and the matching CSS). See the main Speculum docs section **Custom Plugins and Panels** for PHP registration, API routes, Vue panels, and RBAC notes.

## panels.json

```json
[
    "vendor/acme/widgets/resources/speculum",
    "vendor/crustum/speculum-custom/resources/speculum"
]
```

Missing paths abort the build. Override with env `SPECULUM_PANEL_ROOTS` (comma-separated). Other overrides: `SPECULUM_FRONTEND`, `SPECULUM_BUILD_OUTDIR`, `SPECULUM_APP_ROOT` (see `build.mjs` header).
