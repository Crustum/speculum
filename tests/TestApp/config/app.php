<?php
declare(strict_types=1);

/**
 * Test application config.
 */
return [
    'debug' => true,
    'App' => [
        'namespace' => 'TestApp',
        'encoding' => 'UTF-8',
        'defaultLocale' => 'en_US',
        'defaultTimezone' => 'UTC',
        'base' => false,
        'dir' => 'src',
        'webroot' => 'webroot',
        'wwwRoot' => WWW_ROOT,
        'fullBaseUrl' => 'http://localhost',
        'imageBaseUrl' => 'img/',
        'cssBaseUrl' => 'css/',
        'jsBaseUrl' => 'js/',
        'paths' => [
            'plugins' => [ROOT . DS . 'plugins' . DS],
            'templates' => [ROOT . DS . 'templates' . DS],
            'locales' => [RESOURCES . 'locales' . DS],
        ],
    ],
    'Asset' => [
        'timestamp' => false,
        'cacheTime' => '+1 day',
    ],
    'Security' => [
        'salt' => 'speculum-test-security-salt-change-me',
    ],
    'Session' => [
        'defaults' => 'php',
    ],
];
