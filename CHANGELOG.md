# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0]

Initial release of `crustum/speculum` (`Crustum\Speculum`).

CakePHP 5 debug assistant: request/console insight, watchers, dashboard SPA, storage, and a small MCP surface for agents. See `docs/index.md`.

### Added

- Plugin bootstrap, config (`config/speculum.php` / manifest install), Phinx migrations, Tables/Entities
- `Speculum` orchestration: record/filter/tag, pause cache, flush on terminate/shutdown, pending updates job
- `DatabaseEntriesRepository` (store/find/clear/prune/monitor tags)
- Dashboard SPA under Speculum routes + `/speculum/api/*` JSON API (meta, entries, monitored tags)
- Core watchers: Request, Query, Exception, Log, Command, View, Mail, Model, Cache, HttpClient, Event
- Queue watcher: Job (Cake queue processor events)
- Soft watchers (when packages present): Batch (`crustum/batch-queue`), Broadcast, Notification, Schedule (`crustum/cakephp-scheduling`)
- CLI: `speculum clear|pause|resume|prune|mcp`
- MCP server `cake-speculum` with `speculum_search` and `speculum_entry` (use Ignis MCP for schema/tinker)
- Query logger decorator that preserves bindings when stacked with Rhythm’s query logger
- Frontend build (Vue); configurable `Speculum.assets.path` / `dir`; layout links Vite file names directly
- Avatar callback / Gravatar default for entry users
- Centralized `Sanitizer` layer (`SensitiveData`) with config-driven glob patterns for headers, nested params, HttpClient, and query bindings
