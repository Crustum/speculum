<?php
declare(strict_types=1);

$findRoot = function (): string {
    $root = dirname(__DIR__);
    if (is_dir($root . '/vendor/cakephp/cakephp')) {
        return $root;
    }

    $root = dirname(__DIR__, 2);
    if (is_dir($root . '/vendor/cakephp/cakephp')) {
        return $root;
    }

    $root = dirname(__DIR__, 3);
    if (is_dir($root . '/vendor/cakephp/cakephp')) {
        return $root;
    }

    throw new RuntimeException('Cannot find CakePHP vendor directory.');
};

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

define('ROOT', $findRoot());
define('APP_DIR', 'TestApp');
define('WEBROOT_DIR', 'webroot');
define('APP', ROOT . '/tests/TestApp/');
define('CONFIG', ROOT . '/tests/TestApp/config/');
define('WWW_ROOT', ROOT . DS . 'webroot' . DS);
define('TESTS', ROOT . DS . 'tests' . DS);
define('TMP', ROOT . DS . 'tmp' . DS);
define('LOGS', TMP . 'logs' . DS);
define('CACHE', TMP . 'cache' . DS);
define('RESOURCES', ROOT . DS . 'resources' . DS);
define('CAKE_CORE_INCLUDE_PATH', ROOT . '/vendor/cakephp/cakephp');
define('CORE_PATH', CAKE_CORE_INCLUDE_PATH . DS);
define('CAKE', CORE_PATH . 'src' . DS);

require ROOT . '/vendor/cakephp/cakephp/src/functions.php';
require ROOT . '/vendor/autoload.php';

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\Fixture\SchemaLoader;
use Crustum\Speculum\SpeculumPlugin;

Configure::write('debug', true);
Configure::write('App', [
    'namespace' => 'TestApp',
    'encoding' => 'UTF-8',
    'defaultLocale' => 'en_US',
    'defaultTimezone' => 'UTC',
    'base' => false,
    'dir' => 'src',
    'webroot' => 'webroot',
    'wwwRoot' => WWW_ROOT,
    'fullBaseUrl' => 'http://localhost',
    'paths' => [
        'plugins' => [ROOT . DS],
        'templates' => [APP . 'templates' . DS],
        'locales' => [RESOURCES . 'locales' . DS],
    ],
]);
Configure::write('Security', [
    'salt' => 'speculum-test-security-salt-change-me',
]);
Configure::write('Session', [
    'defaults' => 'php',
]);
Configure::write('Asset', [
    'timestamp' => false,
]);
Configure::write('Speculum', [
    'enabled' => true,
    'path' => 'speculum',
    'timezone' => 'UTC',
    'recording' => true,
    'assets' => [
        'path' => 'frontend',
        'dir' => ROOT . DS . 'webroot' . DS . 'frontend',
    ],
    'ignore_commands' => [
        'migrations',
        'migrations migrate',
        'migrations rollback',
        'queue',
        'queue worker',
        'schema_cache',
        'schema_cache clear',
        'schema_cache build',
        'cache',
        'cache clear',
        'cache clear_all',
        'speculum',
    ],
    'ignore_paths' => [
        'debug-kit*',
        'debug_kit*',
        'monitor*',
        'rhythm*',
    ],
    'storage' => [
        'database' => [
            'connection' => 'test',
            'chunk' => 100,
        ],
    ],
    'queue' => [
        'connection' => 'default',
        'queue' => null,
        'delay' => 0,
        'worker_flush_interval' => 0,
        'worker_flush_limit' => 2000,
    ],
    'watchers' => [],
]);

foreach ([TMP, LOGS, CACHE, CACHE . 'models', CACHE . 'persistent', CACHE . 'views', TMP . 'sessions', TMP . 'tests'] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

Cache::setConfig([
    'default' => [
        'className' => 'File',
        'path' => CACHE,
    ],
    '_cake_translations_' => [
        'className' => 'File',
        'prefix' => 'speculum_test_cake_core_',
        'path' => CACHE . 'persistent/',
        'serialize' => true,
        'duration' => '+10 seconds',
    ],
    '_cake_model_' => [
        'className' => 'File',
        'prefix' => 'speculum_test_cake_model_',
        'path' => CACHE . 'models/',
        'serialize' => true,
        'duration' => '+10 seconds',
    ],
]);

if (!getenv('db_dsn')) {
    putenv('db_dsn=sqlite:///:memory:');
}

ConnectionManager::setConfig('test', [
    'url' => getenv('db_dsn'),
    'timezone' => 'UTC',
]);
ConnectionManager::alias('test', 'default');

Plugin::getCollection()->add(new SpeculumPlugin([
    'path' => ROOT . DS,
    'bootstrap' => true,
    'routes' => true,
    'middleware' => true,
]));

$schemaLoader = new SchemaLoader();
$schemaLoader->loadInternalFile(TESTS . 'schema.php');
