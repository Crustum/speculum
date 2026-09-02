<?php
declare(strict_types=1);

use Boundwize\StructArmed\Architecture;

return Architecture::define()
    // Foundation first (first-match wins). Shared DTOs that live in domain
    // folders are pulled down so Contract/Storage stay leaf-clean.
    ->layerPattern('Foundation', [
        '/^Crustum\\\\Speculum\\\\(Enum|Support|Contract|Recording|Sanitizer)(\\\\.*)?$/',
        '/^Crustum\\\\Speculum\\\\Entry\\\\EntryResult$/',
        '/^Crustum\\\\Speculum\\\\Storage\\\\EntryQueryOptions$/',
    ])
    ->layerPattern('Entry', '/^Crustum\\\\Speculum\\\\Entry\\\\.*$/')
    ->layerPattern('Model', '/^Crustum\\\\Speculum\\\\Model\\\\.*$/')
    ->layerPattern('Storage', '/^Crustum\\\\Speculum\\\\Storage\\\\.*$/')
    // Watcher↔Registry↔Queue and Mailer/Event→Watcher form one recording spine.
    ->layerPattern('Listener', '/^Crustum\\\\Speculum\\\\Listener\\\\.*$/')
    ->layerPattern('Watching', [
        '/^Crustum\\\\Speculum\\\\(Watcher|Registry|Queue|Mailer|Event)(\\\\.*)?$/',
    ])
    ->layerPattern('Middleware', '/^Crustum\\\\Speculum\\\\Middleware\\\\.*$/')
    ->layerPattern('Controller', '/^Crustum\\\\Speculum\\\\Controller\\\\.*$/')
    ->layerPattern('Frontend', '/^Crustum\\\\Speculum\\\\Frontend\\\\.*$/')
    ->layerPattern('Mcp', '/^Crustum\\\\Speculum\\\\Mcp\\\\.*$/')
    // Speculum facade is intentionally unregistered: Watchers/Controllers/etc.
    // call Speculum::*; unregistered classes are treated as external.
    ->layerPattern('Plugin', [
        '/^Crustum\\\\Speculum\\\\SpeculumPlugin$/',
        '/^Crustum\\\\Speculum\\\\Command\\\\.*$/',
    ])
    ->ruleset([
        'Foundation' => [],
        'Entry' => ['Foundation'],
        'Model' => ['Foundation'],
        'Storage' => ['Entry', 'Model', 'Foundation'],
        'Watching' => ['Entry', 'Storage', 'Foundation'],
        'Middleware' => ['+Watching'],
        'Controller' => ['+Storage', '+Watching'],
        'Frontend' => ['Foundation'],
        'Listener' => ['Foundation', 'Watching'],
        'Mcp' => ['+Storage', 'Watching'],
        'Plugin' => ['+Controller', '+Mcp', 'Frontend', 'Listener', 'Middleware'],
    ]);
