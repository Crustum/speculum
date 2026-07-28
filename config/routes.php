<?php
declare(strict_types=1);

use Crustum\Speculum\Registry\WatcherRegistry;
use Cake\Routing\RouteBuilder;
use Cake\Routing\Route\DashedRoute;

/**
 * Speculum SPA + JSON API routes under /speculum.
 *
 * @var \Cake\Routing\RouteBuilder $routes
 */
WatcherRegistry::registerDefaultEntryResources();

$routes->plugin('Crustum/Speculum', ['path' => '/speculum'], function (RouteBuilder $routes): void {
    $routes->setRouteClass(DashedRoute::class);

    $routes->scope('/api', function (RouteBuilder $api): void {
        $api->setExtensions([]);

        $api->connect('/meta', ['controller' => 'Meta', 'action' => 'index', '_method' => 'GET']);

        $dedicated = [
            'blazecast' => 'BlazeCast',
            'exceptions' => 'Exceptions',
            'mail' => 'Mail',
        ];

        foreach ($dedicated as $path => $controller) {
            $api->connect('/' . $path, [
                'controller' => $controller,
                'action' => 'index',
                '_method' => 'POST',
            ]);
            $api->connect('/' . $path . '/{id}', [
                'controller' => $controller,
                'action' => 'view',
                '_method' => 'GET',
            ])->setPass(['id']);
        }

        foreach (WatcherRegistry::entryResources() as $path => $resource) {
            $api->connect('/' . $path, [
                'controller' => 'EntryResources',
                'action' => 'index',
                'resource' => $path,
                '_method' => 'POST',
            ]);
            $api->connect('/' . $path . '/{id}', [
                'controller' => 'EntryResources',
                'action' => 'view',
                'resource' => $path,
                '_method' => 'GET',
            ])->setPass(['id']);
        }

        $api->connect('/exceptions/{id}', [
            'controller' => 'Exceptions',
            'action' => 'edit',
            '_method' => 'PUT',
        ])->setPass(['id']);

        $api->connect('/mail/{id}/preview', [
            'controller' => 'Mail',
            'action' => 'preview',
            '_method' => 'GET',
        ])->setPass(['id']);

        $api->connect('/mail/{id}/download', [
            'controller' => 'Mail',
            'action' => 'download',
            '_method' => 'GET',
        ])->setPass(['id']);

        $api->connect('/monitored-tags', [
            'controller' => 'MonitoredTags',
            'action' => 'index',
            '_method' => 'GET',
        ]);
        $api->connect('/monitored-tags', [
            'controller' => 'MonitoredTags',
            'action' => 'add',
            '_method' => 'POST',
        ]);
        $api->connect('/monitored-tags/delete', [
            'controller' => 'MonitoredTags',
            'action' => 'delete',
            '_method' => 'POST',
        ]);

        $api->connect('/toggle-recording', [
            'controller' => 'Recording',
            'action' => 'toggle',
            '_method' => 'POST',
        ]);

        $api->connect('/entries', [
            'controller' => 'Entries',
            'action' => 'delete',
            '_method' => 'DELETE',
        ]);
    });

    $routes->connect('/', ['controller' => 'Home', 'action' => 'index']);
    $routes->connect('/{path}', ['controller' => 'Home', 'action' => 'index'])
        ->setPass(['path'])
        ->setPatterns(['path' => '(?!api(?:/|$)).*']);
});

/**
 * Extension plugin controllers (custom actions beyond list/show).
 * Sibling scope: Cake forbids another plugin default inside plugin('Crustum/Speculum').
 * List/show-only extensions should use registerExtensionPanel(..., ['type' => ...]) instead.
 */
$routes->scope('/speculum/api', function (RouteBuilder $api): void {
    $api->setRouteClass(DashedRoute::class);
    $api->setExtensions([]);

    foreach (WatcherRegistry::extensionApiResources() as $path => $defaults) {
        $api->connect('/' . $path, array_merge($defaults, [
            'action' => 'index',
            '_method' => 'POST',
        ]));
        $api->connect('/' . $path . '/{id}', array_merge($defaults, [
            'action' => 'view',
            '_method' => 'GET',
        ]))->setPass(['id']);
    }
});
