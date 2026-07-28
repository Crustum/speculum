# CakePHP Speculum Plugin

The **Speculum** plugin is a debug assistant for CakePHP applications. It records requests, exceptions, logs, database queries, queued jobs, mail, notifications, cache operations, scheduled tasks, HTTP client calls, and related runtime activity into a searchable dashboard.

Speculum tags entries and groups them by request batch so you can follow a full request story. Soft dependencies enable watchers for batches, notifications, scheduling, and broadcasting when those packages are present. Dashboard access is owned by the host application.

The plugin also exposes a dedicated MCP server (`cake-speculum`) with several tools so AI agents can search and inspect recorded entries without opening the UI.

## Requirements

* PHP 8.4+
* `crustum/mcp`

See [Versions.md](docs/Versions.md) for the supported CakePHP versions.

## Documentation

For documentation, as well as tutorials, see the [docs](docs/index.md) directory of this repository.

## License

Licensed under the [MIT](http://www.opensource.org/licenses/mit-license.php) License. Redistributions of the source code included in this repository must retain the copyright notice found in each file.
