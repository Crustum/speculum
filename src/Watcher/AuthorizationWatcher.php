<?php
declare(strict_types=1);

namespace Crustum\Speculum\Watcher;

use ArrayAccess;
use BackedEnum;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\Routing\Router;
use Cake\Utility\Hash;
use Closure;
use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Watcher\Trait\RouteIgnoreTrait;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionObject;
use UnitEnum;

/**
 * Soft watcher for authorization checks.
 *
 * Records CakeDC Auth RBAC events (`Auth.Rbac.checked`, `Auth.Authorization.checked`)
 * when `SoftFeature::CakeDCAuth` is available, and generic `can()`/`canResult()` checks
 * via the Speculum decorator (`Speculum.Authorization.checked`) when `SoftFeature::Authorization`
 * is available.
 */
class AuthorizationWatcher extends Watcher
{
    use RouteIgnoreTrait;

    /**
     * @var array{allowed: int, denied: int}
     */
    protected array $linkCheckTotals = [
        'allowed' => 0,
        'denied' => 0,
    ];

    /**
     * Request targets already recorded for the current HTTP request (dedupe Superuser + AuthLink).
     *
     * @var array<string, true>
     */
    protected array $recordedTargets = [];

    /**
     * Abilities already recorded via decorator for the current request (dedupe multiple can() calls).
     *
     * @var array<string, true>
     */
    protected array $recordedDecoratorAbilities = [];

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        if (WatcherRegistry::isSoftAvailable(SoftFeature::CakeDCAuth)) {
            EventManager::instance()->on('Auth.Rbac.checked', function (EventInterface $event): void {
                $this->recordFromEvent($event);
            });

            EventManager::instance()->on('Auth.Authorization.checked', function (EventInterface $event): void {
                $this->recordFromEvent($event);
            });
        }

        if (WatcherRegistry::isSoftAvailable(SoftFeature::Authorization)) {
            EventManager::instance()->on('Speculum.Authorization.checked', function (EventInterface $event): void {
                $this->recordFromSpeculumEvent($event);
            });
        }

        EventManager::instance()->on('Server.terminate', function (): void {
            $this->flushLinkCheckSummary();
            $this->recordedTargets = [];
            $this->recordedDecoratorAbilities = [];
        });
    }

    /**
     * Record an authorization check from a Cake event.
     *
     * Handles `Auth.Rbac.checked` and `Auth.Authorization.checked` (Users SuperuserPolicy).
     *
     * @param \Cake\Event\EventInterface<object> $event Authorization event.
     * @return void
     */
    public function recordFromEvent(EventInterface $event): void
    {
        $this->record([
            'allowed' => $event->getData('allowed'),
            'role' => $event->getData('role'),
            'user' => $event->getData('user'),
            'request' => $event->getData('request'),
            'permission' => $event->getData('permission'),
            'reason' => $event->getData('reason'),
            'policy' => $event->getData('policy'),
        ]);
    }

    /**
     * Record an authorization check from the Speculum decorator event.
     *
     * Skipped when another decorator event was already recorded for the same ability.
     *
     * @param \Cake\Event\EventInterface<object> $event Authorization event.
     * @return void
     */
    public function recordFromSpeculumEvent(EventInterface $event): void
    {
        $request = $event->getData('request');
        if ($request instanceof ServerRequestInterface) {
            $ability = $this->resolveAbility($request);
            if ($ability !== null && isset($this->recordedDecoratorAbilities[$ability])) {
                return;
            }

            if ($ability !== null) {
                $this->recordedDecoratorAbilities[$ability] = true;
            }
        }

        $result = $event->getData('result');
        $policyClass = null;
        if (is_object($result) && method_exists($result, 'getPolicy')) {
            $policyClass = $result->getPolicy();
        }

        $this->record([
            'allowed' => $event->getData('allowed'),
            'role' => null,
            'user' => $event->getData('user'),
            'request' => $request,
            'permission' => null,
            'reason' => null,
            'policy' => $policyClass ?? $event->getData('policy_class'),
        ], EntryType::Authorization);
    }

    /**
     * Resolve the ability string (controller/action) from a request.
     */
    protected function resolveAbility(ServerRequestInterface $request): ?string
    {
        $params = (array)$request->getAttribute('params');
        $controller = is_string($params['controller'] ?? null) ? $params['controller'] : '';
        $action = is_string($params['action'] ?? null) ? $params['action'] : '';
        $ability = trim($controller . '/' . $action, '/');

        return $ability !== '' ? $ability : null;
    }

    /**
     * Record a CakeDC Auth RBAC entry from normalized event data.
     *
     * @param array<string, mixed> $data Event data.
     * @param \Crustum\Speculum\Enum\EntryType $type Entry type.
     * @return void
     */
    public function record(array $data, EntryType $type = EntryType::CakeDCAuth): void
    {
        if (!Speculum::isRecording()) {
            return;
        }

        $request = $data['request'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return;
        }

        if ($this->shouldIgnore($request)) {
            return;
        }

        $allowed = (bool)($data['allowed'] ?? false);
        $isActiveRequest = $this->isActiveRequest($request);
        $linkChecks = (string)($this->options['link_checks'] ?? 'off');
        $linkCheck = !$isActiveRequest;

        if ($linkCheck) {
            if ($linkChecks === 'off') {
                return;
            }

            if ($linkChecks === 'summary') {
                $this->linkCheckTotals[$allowed ? 'allowed' : 'denied']++;

                return;
            }

            if ($linkChecks === 'denies' && $allowed) {
                return;
            }

            if ($this->alreadyRecordedTarget($request)) {
                return;
            }
        }

        $this->storeEntry($data, $request, $allowed, $linkCheck, $type);
    }

    /**
     * Flush aggregated AuthLink check counts as a single entry.
     *
     * @return void
     */
    public function flushLinkCheckSummary(): void
    {
        $allowed = $this->linkCheckTotals['allowed'];
        $denied = $this->linkCheckTotals['denied'];
        $this->linkCheckTotals = [
            'allowed' => 0,
            'denied' => 0,
        ];

        if (!Speculum::isRecording() || ($allowed === 0 && $denied === 0)) {
            return;
        }

        $entry = IncomingEntry::make([
            'ability' => 'AuthLink summary',
            'allowed' => $denied === 0,
            'role' => null,
            'reason' => sprintf('AuthLink checks: %d allowed, %d denied', $allowed, $denied),
            'permission' => null,
            'checked' => null,
            'kind' => null,
            'params' => [],
            'user_id' => null,
            'link_check' => true,
            'link_check_summary' => [
                'allowed' => $allowed,
                'denied' => $denied,
            ],
        ])->tags(['link-summary']);

        Speculum::recordEntry(EntryType::CakeDCAuth, $entry);
    }

    /**
     * @param array<string, mixed> $data Event data.
     * @param \Psr\Http\Message\ServerRequestInterface $request Checked request.
     * @param bool $allowed Whether access was allowed.
     * @param bool $linkCheck Whether this was an AuthLink-style synthetic check.
     * @return void
     */
    protected function storeEntry(
        array $data,
        ServerRequestInterface $request,
        bool $allowed,
        bool $linkCheck,
        EntryType $type = EntryType::CakeDCAuth,
    ): void {
        $user = $data['user'] ?? [];
        if (!is_array($user) && !($user instanceof ArrayAccess)) {
            $user = [];
        }

        $params = (array)$request->getAttribute('params');
        $controller = is_string($params['controller'] ?? null) ? $params['controller'] : '';
        $action = is_string($params['action'] ?? null) ? $params['action'] : '';
        $ability = trim($controller . '/' . $action, '/');
        if ($ability === '') {
            $ability = $request->getUri()->getPath() ?: 'unknown';
        }

        $role = $data['role'] ?? null;
        $role = is_string($role) ? $role : null;

        $reason = $data['reason'] ?? null;
        $reason = is_string($reason) ? $reason : null;

        $policy = $data['policy'] ?? null;

        $rawPermission = $data['permission'] ?? null;
        if ($rawPermission !== null && !is_array($rawPermission)) {
            $rawPermission = null;
        }

        $permission = $rawPermission !== null ? $this->normalizePermission($rawPermission) : null;
        $kind = $this->permissionKind($rawPermission);
        $checked = $this->buildCheckedContext($request, $role);

        if ($this->isStructuredMatchReason($reason)) {
            $reason = null;
        }

        $tags = [
            $allowed ? 'allowed' : 'denied',
        ];
        if (is_string($policy) && str_contains($policy, 'Superuser')) {
            $tags[] = 'superuser';
        }

        if ($kind === 'bypass') {
            $tags[] = 'bypass';
        } elseif ($kind === 'rule') {
            $tags[] = 'rule';
        }

        $entry = IncomingEntry::make([
            'ability' => $ability,
            'allowed' => $allowed,
            'role' => $role,
            'reason' => $reason,
            'kind' => $kind,
            'permission' => $permission,
            'checked' => $checked,
            'policy' => is_string($policy) ? $policy : null,
            'params' => [
                'plugin' => $params['plugin'] ?? null,
                'prefix' => $params['prefix'] ?? null,
                'controller' => $params['controller'] ?? null,
                'action' => $params['action'] ?? null,
            ],
            'user_id' => Hash::get((array)$user, 'id'),
            'link_check' => $linkCheck,
        ])->tags($tags);

        Speculum::recordEntry($type, $entry);

        if ($linkCheck) {
            $this->recordedTargets[$this->requestTargetKey($request)] = true;
        }
    }

    /**
     * Build Auth reserved check context (same shape as Rbac `$reserved`).
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Checked request.
     * @param string|null $role Effective role.
     * @return array<string, mixed>
     */
    protected function buildCheckedContext(ServerRequestInterface $request, ?string $role): array
    {
        $params = (array)$request->getAttribute('params');

        return [
            'prefix' => $params['prefix'] ?? null,
            'plugin' => $params['plugin'] ?? null,
            'extension' => $params['_ext'] ?? null,
            'controller' => $params['controller'] ?? null,
            'action' => $params['action'] ?? null,
            'role' => $role,
        ];
    }

    /**
     * Normalize a permission rule for JSON storage (objects → class names only).
     *
     * @param array<string, mixed> $permission Raw permission from Auth.
     * @return array<string, mixed>
     */
    protected function normalizePermission(array $permission): array
    {
        $normalized = [];
        foreach ($permission as $key => $value) {
            $normalized[(string)$key] = $this->normalizePermissionValue($value);
        }

        return $normalized;
    }

    /**
     * @param mixed $value Permission value.
     * @return mixed
     */
    protected function normalizePermissionValue(mixed $value): mixed
    {
        if ($value instanceof Closure) {
            return 'Closure';
        }

        if ($value instanceof UnitEnum) {
            return $value instanceof BackedEnum ? $value->value : $value->name;
        }

        if (is_object($value)) {
            return $this->normalizeRuleObject($value);
        }

        if (is_array($value)) {
            if (isset($value['className']) && is_string($value['className'])) {
                return $this->shortClassName($value['className']);
            }

            if ($this->isListOfRules($value)) {
                return array_values(array_map(
                    $this->normalizePermissionValue(...),
                    $value,
                ));
            }

            $out = [];
            foreach ($value as $key => $item) {
                $out[(string)$key] = $this->normalizePermissionValue($item);
            }

            return $out;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return (string)$value;
    }

    /**
     * Rule / AndRules objects: class names only (no constructor options).
     *
     * @param object $value Rule-like object.
     * @return array<string, mixed>|string
     */
    protected function normalizeRuleObject(object $value): string|array
    {
        $type = $this->shortClassName($value::class);
        $nested = $this->extractNestedRules($value);

        if ($nested === null) {
            return $type;
        }

        return [
            'type' => $type,
            'rules' => array_map(
                $this->normalizePermissionValue(...),
                $nested,
            ),
        ];
    }

    /**
     * @param object $value Rule composite object.
     * @return list<mixed>|null
     */
    protected function extractNestedRules(object $value): ?array
    {
        if (method_exists($value, 'getRules')) {
            $rules = $value->getRules();
            if (is_array($rules)) {
                return array_values($rules);
            }
        }

        $reflection = new ReflectionObject($value);
        if (!$reflection->hasProperty('rules')) {
            return null;
        }

        $property = $reflection->getProperty('rules');

        $rules = $property->getValue($value);
        if (!is_array($rules)) {
            return null;
        }

        return array_values($rules);
    }

    /**
     * @param array<mixed> $value Candidate list.
     * @return bool
     */
    protected function isListOfRules(array $value): bool
    {
        if ($value === [] || !array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (is_object($item)) {
                return true;
            }

            if (is_array($item) && isset($item['className'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed>|null $permission Raw permission.
     * @return string|null bypass|rule|bool
     */
    protected function permissionKind(?array $permission): ?string
    {
        if ($permission === null) {
            return null;
        }

        $bypass = $permission['bypassAuth'] ?? false;
        if ($bypass === true || $bypass instanceof Closure || (is_object($bypass) && !$bypass instanceof UnitEnum)) {
            return 'bypass';
        }

        if (!array_key_exists('allowed', $permission)) {
            return 'bool';
        }

        $allowed = $permission['allowed'];
        if (is_object($allowed)) {
            return 'rule';
        }

        if (is_array($allowed) && isset($allowed['className'])) {
            return 'rule';
        }

        if (is_array($allowed) && $this->isListOfRules($allowed)) {
            return 'rule';
        }

        return 'bool';
    }

    /**
     * Auth debug reason that duplicates checked + permission JSON.
     *
     * @param string|null $reason Raw reason.
     * @return bool
     */
    protected function isStructuredMatchReason(?string $reason): bool
    {
        if ($reason === null || $reason === '') {
            return false;
        }

        return (bool)preg_match('/^For .+ --> Rule matched/s', $reason);
    }

    /**
     * @param string $class FQCN.
     * @return string
     */
    protected function shortClassName(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }

    /**
     * Whether this active-request target was already recorded in the current request.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Checked request.
     * @return bool
     */
    protected function alreadyRecordedTarget(ServerRequestInterface $request): bool
    {
        return isset($this->recordedTargets[$this->requestTargetKey($request)]);
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request Checked request.
     * @return string
     */
    protected function requestTargetKey(ServerRequestInterface $request): string
    {
        return $request->getMethod() . ' ' . $request->getRequestTarget();
    }

    /**
     * Whether the checked request is the active HTTP request (not AuthLink synthetic).
     *
     * Compare method + request target, not object identity: Authentication/Authorization
     * middleware replace the request via withAttribute(), so === Router::getRequest() fails.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Checked request.
     * @return bool
     */
    protected function isActiveRequest(ServerRequestInterface $request): bool
    {
        $active = Router::getRequest();
        if (!$active instanceof ServerRequestInterface) {
            return false;
        }

        if ($request === $active) {
            return true;
        }

        return $request->getMethod() === $active->getMethod()
            && $request->getRequestTarget() === $active->getRequestTarget();
    }
}
