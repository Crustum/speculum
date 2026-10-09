# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0]

### Added

- CakeDC Authorization watcher (ability-based dedup per controller/action)
- Authorization gate: `PolicyResolver`, `SpeculumAuthorizationServiceDecorator` (intercepts `can()`/`canResult()`), `SpeculumAuthorizationMiddleware`, and `EntryType::Authorization`
- AiWatcher: records Crustum/Ai `Ai.*` events (agent/tool/generation/store/failover) as `ai` entries with category/provider/model/invocation tags
- Structured route-ignore for Authorization/Request watchers via shared `RouteIgnoreTrait` (plugin/prefix/controller/action, `*` wildcard)
- Store entries `content` as native JSON (jsonb + GIN on Postgres, JSON on MySQL)
- Event-based flush: `SpeculumFlushEvent` + `StorageListener` listener
- Reorganize Mongo watcher into src/Watcher/Mongo/ (CrustumMongoWatcher,
  MongoCommandSubscriber, MongoQueryLogEngine, MongoQueryLogWatcher,
  MongoWatcher, SpeculumMongoQueryLogger)
- RequestWatcher/QueryWatcher: ignore_content_types to skip body recording for
  streaming responses (text/event-stream from AI agents)
- Frontend: command formatter pane, command details tabs, base64/formatPhp
  utils, mongo query screens, AI + Authorization nav/routes
- Frontend:  new AI Invocation Screen
- AI Watcher & Middleware: improved SpeculumRecordingMiddleware and AiWatcher for better AI event tracking

### Fixed
- Capture earliest request start (plugin bootstrap / `Application.buildContainer`) for accurate duration
- Move `SpeculumRecordingMiddleware` before `ErrorHandlerMiddleware` to capture controller errors
- Merge entry resources by path so renamed types both appear in the index
- HTTP recording no longer starts at container build: `Speculum::start()`
  deferred `startRecording()` to `SpeculumRecordingMiddleware` (which owns the
  per-request `beginRequest()` boundary). Previously bootstrap /
  middleware-queue construction activity (e.g. the AssetCompress config cache
  hit) was recorded and then dropped as "stale entries from a previous request
  cycle" on every request, on every SAPI.

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
