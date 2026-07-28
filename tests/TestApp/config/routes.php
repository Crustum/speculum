<?php
declare(strict_types=1);

/**
 * Test app routes — plugin routes load via Crustum/Speculum.
 */

use Cake\Routing\RouteBuilder;

/** @var \Cake\Routing\RouteBuilder $routes */
$routes->scope('/', function (RouteBuilder $builder): void {
    $builder->fallbacks();
});
