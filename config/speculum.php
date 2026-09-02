<?php
declare(strict_types=1);

use Crustum\Speculum\Watcher\AiWatcher;
use Crustum\Speculum\Watcher\AuthorizationWatcher;
use Crustum\Speculum\Watcher\BatchWatcher;
use Crustum\Speculum\Watcher\BlazeCastWatcher;
use Crustum\Speculum\Watcher\BroadcastWatcher;
use Crustum\Speculum\Watcher\CacheWatcher;
use Crustum\Speculum\Watcher\CommandWatcher;
use Crustum\Speculum\Watcher\EventWatcher;
use Crustum\Speculum\Watcher\ExceptionWatcher;
use Crustum\Speculum\Watcher\HttpClientWatcher;
use Crustum\Speculum\Watcher\LogWatcher;
use Crustum\Speculum\Watcher\MailWatcher;
use Crustum\Speculum\Watcher\ModelWatcher;
use Crustum\Speculum\Watcher\Mongo\CrustumMongoWatcher;
use Crustum\Speculum\Watcher\Mongo\MongoQueryLogWatcher;
use Crustum\Speculum\Watcher\Mongo\MongoWatcher;
use Crustum\Speculum\Watcher\NotificationWatcher;
use Crustum\Speculum\Watcher\QueryWatcher;
use Crustum\Speculum\Watcher\Queue\DereuromarkJobWatcher;
use Crustum\Speculum\Watcher\Queue\JobWatcher;
use Crustum\Speculum\Watcher\Queue\QueuesadillaJobWatcher;
use Crustum\Speculum\Watcher\RequestWatcher;
use Crustum\Speculum\Watcher\ScheduleWatcher;
use Crustum\Speculum\Watcher\SearchesWatcher;
use Crustum\Speculum\Watcher\VarDumpWatcher;
use Crustum\Speculum\Watcher\ViewWatcher;

/**
 * Speculum plugin configuration (Cake Configure style).
 *
 * Loaded as Configure key "Speculum".
 */
return [
    'Speculum' => [
        'enabled' => filter_var(env('SPECULUM_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'domain' => env('SPECULUM_DOMAIN', null),
        'path' => env('SPECULUM_PATH', 'speculum'),
        'driver' => env('SPECULUM_DRIVER', 'database'),
        'assets' => [
            'path' => env('SPECULUM_ASSETS_PATH', 'speculum'),
            'dir' => env('SPECULUM_ASSETS_DIR', null),
        ],
        'storage' => [
            'database' => [
                'connection' => env('SPECULUM_DB_CONNECTION', 'default'),
                'chunk' => (int)env('SPECULUM_CHUNK', 1000),
            ],
        ],
        'queue' => [
            'transport' => env('SPECULUM_QUEUE_TRANSPORT', 'auto'),
            'connection' => env('SPECULUM_QUEUE_CONNECTION', 'default'),
            'queue' => env('SPECULUM_QUEUE', null),
            'delay' => (int)env('SPECULUM_QUEUE_DELAY', 10),
            'worker_flush_interval' => (float)env('SPECULUM_WORKER_FLUSH_INTERVAL', 1),
            'worker_flush_limit' => (int)env('SPECULUM_WORKER_FLUSH_LIMIT', 2000),
        ],
        'middleware' => [],
        'sanitize' => [
            'headers' => [
                'authorization',
                'proxy-authorization',
                'php-auth-pw',
                'cookie',
                'set-cookie',
                'x-xsrf-token',
                'x-csrf-token',
            ],
            'parameters' => [
                '*password*',
                '*token*',
                '*secret*',
                '*api_key*',
                '*apikey*',
            ],
            'response_parameters' => [
                '*password*',
                '*token*',
                '*secret*',
                '*api_key*',
                '*apikey*',
            ],
            'model_attributes' => [
                '*password*',
                '*token*',
                '*secret*',
                '*api_key*',
                '*apikey*',
            ],
            'vardump' => [
                '*password*',
                '*token*',
                '*secret*',
                '*api_key*',
                '*apikey*',
            ],
            'bindings' => [
                'omit' => filter_var(env('SPECULUM_OMIT_QUERY_BINDINGS', false), FILTER_VALIDATE_BOOLEAN),
                'patterns' => [
                    '*password*',
                    '*token*',
                    '*secret*',
                    '*api_key*',
                    '*apikey*',
                ],
            ],
        ],
        'only_paths' => [],
        'ignore_paths' => [
            '.well-known*',
            'debug-kit*',
            'debug_kit*',
            'monitor*',
            'rhythm*',
        ],
        'ignore_commands' => [
            'migrations',
            'migrations migrate',
            'migrations rollback',
            'queue',
            'queue worker',
            'queue run',
            'queuesadilla',
            'rhythm',
            'rhythm check',
            'schema_cache',
            'schema_cache clear',
            'schema_cache build',
            'cache',
            'cache clear',
            'cache clear_all',
        ],
        'watchers' => [
            AiWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_AI_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'slow' => (float)env('SPECULUM_AI_SLOW', 1000),
                'ignore' => [],
                'categories' => ['agent', 'tool', 'generation', 'store', 'file', 'failover'],
            ],
            BatchWatcher::class => filter_var(env('SPECULUM_BATCH_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
            BroadcastWatcher::class => filter_var(env('SPECULUM_BROADCAST_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
            BlazeCastWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_BLAZECAST_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'deliveries' => filter_var(env('SPECULUM_BLAZECAST_DELIVERIES', true), FILTER_VALIDATE_BOOLEAN),
                'messages' => filter_var(env('SPECULUM_BLAZECAST_MESSAGES', true), FILTER_VALIDATE_BOOLEAN),
            ],
            CacheWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_CACHE_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'hidden' => [],
                'ignore_framework' => true,
                'ignore' => [
                    'cake_blazecast:*',
                    'rhythm*',
                ],
            ],
            AuthorizationWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_AUTHORIZATION_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'link_checks' => env('SPECULUM_AUTHORIZATION_LINK_CHECKS', 'off'),
                'ignore' => [
                    ['plugin' => 'DebugKit'],
                    ['plugin' => 'Crustum/Speculum'],
                    ['plugin' => 'Crustum/Ignis'],
                ],
            ],
            HttpClientWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_HTTP_CLIENT_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'ignore_hosts' => [],
            ],
            CommandWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_COMMAND_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'ignore' => [],
                'slow' => (float)env('SPECULUM_COMMAND_SLOW', 1000),
            ],
            EventWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_EVENT_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'events' => [],
                'ignore' => [],
            ],
            ExceptionWatcher::class => filter_var(env('SPECULUM_EXCEPTION_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
            VarDumpWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_VARDUMP_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'max_items' => 250,
                'max_string' => 5000,
                'max_bytes' => 65536,
            ],
            JobWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_JOB_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'slow' => (float)env('SPECULUM_JOB_SLOW', 1000),
            ],
            QueuesadillaJobWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_QUEUESADILLA_JOB_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'slow' => (float)env('SPECULUM_JOB_SLOW', 1000),
            ],
            DereuromarkJobWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_DEREUROMARK_JOB_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'slow' => (float)env('SPECULUM_JOB_SLOW', 1000),
            ],
            LogWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_LOG_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'level' => env('SPECULUM_LOG_LEVEL', 'error'),
                'scopes' => null,
                'include_unscoped' => true,
            ],
            MailWatcher::class => filter_var(env('SPECULUM_MAIL_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
            ModelWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_MODEL_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'events' => ['Model.afterSave', 'Model.afterDelete'],
                'hydrations' => true,
                'ignore_connections' => [
                    'debug_kit',
                ],
                'ignore_namespaces' => [
                    'DebugKit\\',
                ],
            ],
            MongoWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_MONGO_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'slow' => (float)env('SPECULUM_MONGO_SLOW', 100),
                'ignore_commands' => [
                    'hello',
                    'ismaster',
                    'isMaster',
                    'ping',
                    'endSessions',
                    'buildInfo',
                    'saslStart',
                    'saslContinue',
                    'getMore',
                    'listCollections',
                    'listIndexes',
                    'listDatabases',
                    'collStats',
                    'dbStats',
                    'abortTransaction',
                    'commitTransaction',
                    'startTransaction',
                ],
            ],
            CrustumMongoWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_CRUSTUM_MONGO_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'ignore_connections' => [
                    'debug_kit',
                    'test_mongo',
                    'test',
                ],
                'slow' => (float)env('SPECULUM_CRUSTUM_MONGO_SLOW', 100),
            ],
            MongoQueryLogWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_MONGO_QUERY_LOG_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'ignore_connections' => [
                    'debug_kit',
                    'test_mongo',
                    'test',
                ],
                'scopes' => ['mongoQueriesLog', 'mongo.database.queries'],
                'slow' => (float)env('SPECULUM_MONGO_QUERY_LOG_SLOW', 100),
            ],
            NotificationWatcher::class => filter_var(env('SPECULUM_NOTIFICATION_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
            SearchesWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_SEARCHES_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'slow' => (float)env('SPECULUM_SEARCHES_SLOW', 100),
                'request' => filter_var(env('SPECULUM_SEARCHES_REQUEST', true), FILTER_VALIDATE_BOOLEAN),
                'response' => filter_var(env('SPECULUM_SEARCHES_RESPONSE', true), FILTER_VALIDATE_BOOLEAN),
            ],
            QueryWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_QUERY_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'ignore_packages' => true,
                'ignore_paths' => [],
                'ignore_connections' => [
                    'debug_kit',
                ],
                'slow' => (float)env('SPECULUM_QUERY_SLOW', 100),
            ],
            RequestWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_REQUEST_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'size_limit' => (int)env('SPECULUM_RESPONSE_SIZE_LIMIT', 64),
                'ignore_streamable' => filter_var(env('SPECULUM_IGNORE_STREAMABLE', true), FILTER_VALIDATE_BOOLEAN),
                'ignore_http_methods' => [],
                'ignore_status_codes' => [],
                'ignore_content_types' => [
                    'text/event-stream',
                ],
                'ignore' => [
                    ['plugin' => 'Crustum/Speculum'],
                    ['plugin' => 'Crustum/Ignis'],
                ],
                'slow' => (float)env('SPECULUM_REQUEST_SLOW', 1000),
            ],
            ScheduleWatcher::class => filter_var(env('SPECULUM_SCHEDULE_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
            ViewWatcher::class => [
                'enabled' => filter_var(env('SPECULUM_VIEW_WATCHER', true), FILTER_VALIDATE_BOOLEAN),
                'ignore_paths' => [
                    '*/DebugKit/*',
                    '*/cakephp/debug_kit/*',
                ],
            ],
        ],
    ],
];
