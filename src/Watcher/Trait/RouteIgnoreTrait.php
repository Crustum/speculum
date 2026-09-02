<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher\Trait;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Shareable "ignore by route" logic for watchers that record per-request
 * entries (requests, authorization checks, …).
 */
trait RouteIgnoreTrait
{
    /**
     * Whether the request's plugin/prefix/controller/action matches a configured
     * ignore rule. Rules may be:
     *  - a string glob matched against `plugin/controller/action` (legacy), or
     *  - an associative array of `plugin`/`prefix`/`controller`/`action` globs,
     *    where every given component must match (so `['plugin' => 'X',
     *    'controller' => 'Y']` means plugin X AND controller Y). A `*` pattern
     *    matches any value (RBAC-style: `*` = "any"), including empty.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Checked request.
     * @return bool
     */
    protected function shouldIgnore(ServerRequestInterface $request): bool
    {
        $ignore = $this->options['ignore'] ?? [];
        if ($ignore === []) {
            return false;
        }

        $params = (array)$request->getAttribute('params');
        $components = [
            'plugin' => is_string($params['plugin'] ?? null) ? $params['plugin'] : '',
            'prefix' => is_string($params['prefix'] ?? null) ? $params['prefix'] : '',
            'controller' => is_string($params['controller'] ?? null) ? $params['controller'] : '',
            'action' => is_string($params['action'] ?? null) ? $params['action'] : '',
        ];

        foreach ($ignore as $rule) {
            if (is_string($rule)) {
                $key = trim(
                    ($components['plugin'] !== '' ? $components['plugin'] . '/' : '')
                        . $components['controller'] . '/' . $components['action'],
                    '/',
                );
                if (fnmatch($rule, $key, FNM_CASEFOLD)) {
                    return true;
                }

                continue;
            }

            if (!is_array($rule)) {
                continue;
            }

            $matchedAll = true;
            foreach (['plugin', 'prefix', 'controller', 'action'] as $part) {
                if (!array_key_exists($part, $rule)) {
                    continue;
                }

                $pattern = is_string($rule[$part]) ? $rule[$part] : '';
                if (!fnmatch($pattern, $components[$part], FNM_CASEFOLD)) {
                    $matchedAll = false;

                    break;
                }
            }

            if ($matchedAll) {
                return true;
            }
        }

        return false;
    }
}
