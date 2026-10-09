<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Authorization\Policy\Result;
use Cake\Event\Event;
use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\AuthorizationWatcher;

/**
 * Authorization watcher coverage (Auth.Rbac.checked + Speculum.Authorization.checked).
 */
class AuthorizationWatcherTest extends TestCaseBase
{
    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        Router::reload();
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testRecordsActiveRequestCheck(): void
    {
        $request = new ServerRequest([
            'url' => '/users/view/1',
            'params' => [
                'plugin' => null,
                'controller' => 'Users',
                'action' => 'view',
                'prefix' => null,
            ],
        ]);
        Router::setRequest($request);

        $authorizedRequest = $request->withAttribute('identity', ['id' => 3]);

        $watcher = new AuthorizationWatcher(['link_checks' => 'off']);
        $watcher->record([
            'allowed' => true,
            'role' => 'admin',
            'user' => ['id' => 3],
            'request' => $authorizedRequest,
            'permission' => [
                'controller' => 'Users',
                'action' => 'view',
                'allowed' => true,
            ],
            'reason' => 'Rule matched',
        ]);

        $this->assertCount(1, Speculum::$entriesQueue);
        $this->assertContains('allowed', Speculum::$entriesQueue[0]->tags);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(EntryType::CakeDCAuth->value, $entries[0]->type);
        $this->assertSame('Users/view', $entries[0]->content['ability']);
        $this->assertTrue($entries[0]->content['allowed']);
        $this->assertSame('admin', $entries[0]->content['role']);
        $this->assertSame(3, $entries[0]->content['user_id']);
        $this->assertFalse($entries[0]->content['link_check']);
        $this->assertSame('bool', $entries[0]->content['kind']);
        $this->assertSame('Users', $entries[0]->content['checked']['controller']);
        $this->assertSame('admin', $entries[0]->content['checked']['role']);
    }

    /**
     * @return void
     */
    public function testIgnoreSkipsByPluginControllerAndAction(): void
    {
        $spec = new ServerRequest([
            'url' => '/speculum/entry-resources/view/1',
            'params' => [
                'plugin' => 'Crustum/Speculum',
                'controller' => 'EntryResources',
                'action' => 'view',
            ],
        ]);
        Router::setRequest($spec);

        $watcher = new AuthorizationWatcher([
            'link_checks' => 'off',
            'ignore' => [
                ['plugin' => 'DebugKit'],
                ['plugin' => 'Crustum/Speculum'],
                ['controller' => 'Users', 'action' => 'login'],
            ],
        ]);
        $watcher->record([
            'allowed' => true,
            'role' => 'admin',
            'user' => ['id' => 3],
            'request' => $spec,
        ]);
        $this->assertCount(0, Speculum::$entriesQueue);

        $debugKit = new ServerRequest([
            'url' => '/debug_kit/requests',
            'params' => [
                'plugin' => 'DebugKit',
                'controller' => 'Requests',
                'action' => 'index',
            ],
        ]);
        Router::setRequest($debugKit);
        $watcher->record([
            'allowed' => true,
            'role' => 'admin',
            'user' => ['id' => 3],
            'request' => $debugKit,
        ]);
        $this->assertCount(0, Speculum::$entriesQueue);

        $usersView = new ServerRequest([
            'url' => '/users/view/1',
            'params' => [
                'controller' => 'Users',
                'action' => 'view',
            ],
        ]);
        Router::setRequest($usersView);
        $watcher->record([
            'allowed' => true,
            'role' => 'admin',
            'user' => ['id' => 3],
            'request' => $usersView,
        ]);
        $this->assertCount(1, Speculum::$entriesQueue);

        $usersLogin = new ServerRequest([
            'url' => '/users/login',
            'params' => [
                'controller' => 'Users',
                'action' => 'login',
            ],
        ]);
        Router::setRequest($usersLogin);
        $watcher->record([
            'allowed' => true,
            'role' => 'admin',
            'user' => ['id' => 3],
            'request' => $usersLogin,
        ]);
        $this->assertCount(1, Speculum::$entriesQueue);
    }

    /**
     * @return void
     */
    public function testIgnoreSupportsWildcardLikeRbac(): void
    {
        $watcher = new AuthorizationWatcher([
            'link_checks' => 'off',
            'ignore' => [
                ['plugin' => 'Crustum/Speculum', 'controller' => '*', 'action' => '*'],
                ['controller' => '*', 'action' => 'login'],
            ],
        ]);

        $spec = new ServerRequest([
            'url' => '/speculum/x',
            'params' => [
                'plugin' => 'Crustum/Speculum',
                'controller' => 'EntryResources',
                'action' => 'view',
            ],
        ]);
        Router::setRequest($spec);
        $watcher->record([
            'allowed' => true,
            'role' => 'admin',
            'user' => ['id' => 3],
            'request' => $spec,
        ]);
        $this->assertCount(0, Speculum::$entriesQueue);

        $login = new ServerRequest([
            'url' => '/admin/users/login',
            'params' => [
                'prefix' => 'admin',
                'controller' => 'Users',
                'action' => 'login',
            ],
        ]);
        Router::setRequest($login);
        $watcher->record([
            'allowed' => true,
            'role' => 'admin',
            'user' => ['id' => 3],
            'request' => $login,
        ]);
        $this->assertCount(0, Speculum::$entriesQueue);

        $other = new ServerRequest([
            'url' => '/articles/index',
            'params' => [
                'controller' => 'Articles',
                'action' => 'index',
            ],
        ]);
        Router::setRequest($other);
        $watcher->record([
            'allowed' => true,
            'role' => 'admin',
            'user' => ['id' => 3],
            'request' => $other,
        ]);
        $this->assertCount(1, Speculum::$entriesQueue);
    }

    /**
     * Rule objects store short class names only (no constructor options).
     *
     * @return void
     */
    public function testNormalizesRuleObjectsToClassNames(): void
    {
        $request = new ServerRequest([
            'url' => '/amendments/addendum',
            'params' => [
                'controller' => 'Amendments',
                'action' => 'addendum',
            ],
        ]);
        Router::setRequest($request);

        $inner = new class {
        };

        $composite = new class ($inner) {
            /**
             * @param object $inner Nested rule.
             */
            public function __construct(object $inner)
            {
                $this->rules = [$inner];
            }

            /**
             * @var list<object>
             */
            protected array $rules;
        };

        $watcher = new AuthorizationWatcher(['link_checks' => 'off']);
        $watcher->record([
            'allowed' => true,
            'role' => 'referring',
            'user' => ['id' => 1],
            'request' => $request,
            'permission' => [
                'role' => 'referring',
                'controller' => 'Amendments',
                'action' => 'addendum',
                'allowed' => $composite,
            ],
            'reason' => 'For {"controller":"Amendments"} --> Rule matched {"allowed":{}} with result = 1',
        ]);

        $content = Speculum::$entriesQueue[0]->content;
        $this->assertNull($content['reason']);
        $this->assertSame('rule', $content['kind']);
        $this->assertIsArray($content['permission']['allowed']);
        $this->assertArrayHasKey('type', $content['permission']['allowed']);
        $this->assertArrayHasKey('rules', $content['permission']['allowed']);
        $this->assertCount(1, $content['permission']['allowed']['rules']);
        $this->assertIsString($content['permission']['allowed']['type']);
        $this->assertIsString($content['permission']['allowed']['rules'][0]);
    }

    /**
     * @return void
     */
    public function testNormalizesBypassAuth(): void
    {
        $request = new ServerRequest([
            'url' => '/media/img/view',
            'params' => [
                'plugin' => 'CakeDC/Media',
                'controller' => 'Img',
                'action' => 'view',
                'prefix' => false,
            ],
        ]);
        Router::setRequest($request);

        $watcher = new AuthorizationWatcher(['link_checks' => 'off']);
        $watcher->record([
            'allowed' => true,
            'role' => 'user',
            'user' => [],
            'request' => $request,
            'permission' => [
                'plugin' => ['CakeDC/Media'],
                'prefix' => false,
                'controller' => ['Img'],
                'action' => ['view'],
                'bypassAuth' => true,
            ],
            'reason' => 'For {"plugin":"CakeDC\\/Media"} --> Rule matched {"bypassAuth":true} with result = 1',
        ]);

        $content = Speculum::$entriesQueue[0]->content;
        $this->assertNull($content['reason']);
        $this->assertSame('bypass', $content['kind']);
        $this->assertTrue($content['permission']['bypassAuth']);
        $this->assertContains('bypass', Speculum::$entriesQueue[0]->tags);
    }

    /**
     * Array className configs store the short class name and drop options.
     *
     * @return void
     */
    public function testNormalizesClassNameConfigWithoutOptions(): void
    {
        $request = new ServerRequest([
            'url' => '/instructor/courses/edit',
            'params' => [
                'prefix' => 'Instructor',
                'controller' => 'Courses',
                'action' => 'editCourseContact',
            ],
        ]);
        Router::setRequest($request);

        $watcher = new AuthorizationWatcher(['link_checks' => 'off']);
        $watcher->record([
            'allowed' => true,
            'role' => 'instructor',
            'user' => ['id' => 2],
            'request' => $request,
            'permission' => [
                'controller' => ['Courses'],
                'action' => ['editCourseContact'],
                'allowed' => [
                    'className' => 'App\\Auth\\Rules\\InstructorEditCourseContact',
                    'options' => ['secret' => 'nope'],
                ],
            ],
            'reason' => 'plain keep',
        ]);

        $content = Speculum::$entriesQueue[0]->content;
        $this->assertSame('plain keep', $content['reason']);
        $this->assertSame('rule', $content['kind']);
        $this->assertSame('InstructorEditCourseContact', $content['permission']['allowed']);
    }

    /**
     * @return void
     */
    public function testSkipsAuthLinkChecksWhenLinkChecksOff(): void
    {
        $active = new ServerRequest(['url' => '/dashboard']);
        Router::setRequest($active);

        $linkRequest = new ServerRequest([
            'url' => '/users/edit/1',
            'params' => [
                'controller' => 'Users',
                'action' => 'edit',
            ],
        ]);

        $watcher = new AuthorizationWatcher(['link_checks' => 'off']);
        $watcher->record([
            'allowed' => true,
            'role' => 'user',
            'user' => ['id' => 1],
            'request' => $linkRequest,
            'permission' => ['bypassAuth' => true],
            'reason' => 'AuthLink check',
        ]);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(0, $entries);
    }

    /**
     * @return void
     */
    public function testRecordsDeniedAuthLinkWhenLinkChecksDenies(): void
    {
        $active = new ServerRequest(['url' => '/dashboard']);
        Router::setRequest($active);

        $linkRequest = new ServerRequest([
            'url' => '/admin/users',
            'params' => [
                'controller' => 'Users',
                'action' => 'index',
                'prefix' => 'Admin',
            ],
        ]);

        $watcher = new AuthorizationWatcher(['link_checks' => 'denies']);
        $watcher->record([
            'allowed' => false,
            'role' => 'user',
            'user' => ['id' => 1],
            'request' => $linkRequest,
            'permission' => null,
            'reason' => 'No permission rule matched',
        ]);

        $this->assertCount(1, Speculum::$entriesQueue);
        $this->assertTrue(Speculum::$entriesQueue[0]->content['link_check']);
        $this->assertFalse(Speculum::$entriesQueue[0]->content['allowed']);
        $this->assertContains('denied', Speculum::$entriesQueue[0]->tags);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
    }

    /**
     * @return void
     */
    public function testLinkCheckSummaryFlushesAggregatedCounts(): void
    {
        $active = new ServerRequest(['url' => '/dashboard']);
        Router::setRequest($active);

        $watcher = new AuthorizationWatcher(['link_checks' => 'summary']);
        $linkA = new ServerRequest(['url' => '/a', 'params' => ['controller' => 'A', 'action' => 'index']]);
        $linkB = new ServerRequest(['url' => '/b', 'params' => ['controller' => 'B', 'action' => 'index']]);

        $watcher->record([
            'allowed' => true,
            'role' => 'user',
            'user' => ['id' => 1],
            'request' => $linkA,
            'permission' => [],
            'reason' => 'ok',
        ]);
        $watcher->record([
            'allowed' => false,
            'role' => 'user',
            'user' => ['id' => 1],
            'request' => $linkB,
            'permission' => null,
            'reason' => 'deny',
        ]);

        $this->assertCount(0, $this->loadSpeculumEntries());

        $watcher->flushLinkCheckSummary();
        $this->assertCount(1, Speculum::$entriesQueue);
        $this->assertSame('AuthLink summary', Speculum::$entriesQueue[0]->content['ability']);
        $this->assertSame(['allowed' => 1, 'denied' => 1], Speculum::$entriesQueue[0]->content['link_check_summary']);
        $this->assertContains('link-summary', Speculum::$entriesQueue[0]->tags);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
    }

    /**
     * @return void
     */
    public function testRecordFromRbacEvent(): void
    {
        $request = new ServerRequest([
            'url' => '/login',
            'params' => [
                'plugin' => 'CakeDC/Users',
                'controller' => 'Users',
                'action' => 'login',
            ],
        ]);
        Router::setRequest($request);

        $watcher = new AuthorizationWatcher(['link_checks' => 'off']);
        $watcher->recordFromEvent(new Event('Auth.Rbac.checked', null, [
            'allowed' => true,
            'role' => 'user',
            'user' => [],
            'request' => $request,
            'permission' => [
                'action' => ['login'],
                'bypassAuth' => true,
                'allowed' => true,
            ],
            'reason' => 'For login --> Rule matched',
        ]));

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('Users/login', $entries[0]->content['ability']);
        $this->assertSame('CakeDC/Users', $entries[0]->content['params']['plugin']);
        $this->assertTrue($entries[0]->content['permission']['bypassAuth']);
        $this->assertSame('bypass', $entries[0]->content['kind']);
        $this->assertNull($entries[0]->content['reason']);
    }

    /**
     * @return void
     */
    public function testRecordFromAuthorizationSuperuserEvent(): void
    {
        $request = new ServerRequest([
            'url' => '/admin/users',
            'params' => [
                'plugin' => null,
                'prefix' => 'Admin',
                'controller' => 'Users',
                'action' => 'index',
            ],
        ]);
        Router::setRequest($request);

        $watcher = new AuthorizationWatcher(['link_checks' => 'off']);
        $watcher->recordFromEvent(new Event('Auth.Authorization.checked', null, [
            'allowed' => true,
            'policy' => 'CakeDC\\Auth\\Policy\\SuperuserPolicy',
            'role' => 'admin',
            'user' => ['id' => '8c7d9503-fbfa-4f82-b02c-cd1a79f2cb20', 'is_superuser' => true],
            'request' => $request->withAttribute('identity', ['id' => 1]),
            'permission' => null,
            'reason' => 'SuperuserPolicy allowed',
        ]));

        $this->assertCount(1, Speculum::$entriesQueue);
        $this->assertContains('superuser', Speculum::$entriesQueue[0]->tags);
        $this->assertSame('Users/index', Speculum::$entriesQueue[0]->content['ability']);
        $this->assertSame('SuperuserPolicy allowed', Speculum::$entriesQueue[0]->content['reason']);
    }

    /**
     * @return void
     */
    public function testRecordFromSpeculumEventCreatesAuthorizationEntry(): void
    {
        $request = new ServerRequest([
            'url' => '/docs/credentials',
            'params' => [
                'plugin' => null,
                'controller' => 'Credentials',
                'action' => 'index',
            ],
        ]);
        Router::setRequest($request);

        $result = new Result(true);

        $watcher = new AuthorizationWatcher(['link_checks' => 'off']);
        $watcher->recordFromSpeculumEvent(new Event('Speculum.Authorization.checked', null, [
            'allowed' => true,
            'user' => ['id' => 1],
            'request' => $request,
            'result' => $result,
            'policy_class' => 'CakeDC\\Auth\\Policy\\CollectionPolicy',
        ]));

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertSame(EntryType::Authorization->value, $entries[0]->type);
        $this->assertSame('Credentials/index', $entries[0]->content['ability']);
        $this->assertTrue($entries[0]->content['allowed']);
        $this->assertSame('CakeDC\\Auth\\Policy\\CollectionPolicy', $entries[0]->content['policy']);
    }

    /**
     * Decorator deduplicates by ability — same ability recorded only once.
     *
     * @return void
     */
    public function testDecoratorDeduplicatesByAbility(): void
    {
        $request = new ServerRequest([
            'url' => '/docs/credentials',
            'params' => [
                'plugin' => null,
                'controller' => 'Credentials',
                'action' => 'index',
            ],
        ]);
        Router::setRequest($request);

        $result = new Result(true);

        $watcher = new AuthorizationWatcher(['link_checks' => 'off']);

        $watcher->recordFromSpeculumEvent(new Event('Speculum.Authorization.checked', null, [
            'allowed' => true,
            'user' => ['id' => 1],
            'request' => $request,
            'result' => $result,
            'policy_class' => 'CakeDC\\Auth\\Policy\\CollectionPolicy',
        ]));

        $watcher->recordFromSpeculumEvent(new Event('Speculum.Authorization.checked', null, [
            'allowed' => false,
            'user' => ['id' => 1],
            'request' => $request,
            'result' => new Result(false),
            'policy_class' => 'CakeDC\\Auth\\Policy\\CollectionPolicy',
        ]));

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
        $this->assertTrue($entries[0]->content['allowed']);
    }

    /**
     * Decorator does NOT dedup across different abilities.
     *
     * @return void
     */
    public function testDecoratorRecordsDifferentAbilities(): void
    {
        $requestA = new ServerRequest([
            'url' => '/credentials/index',
            'params' => ['controller' => 'Credentials', 'action' => 'index'],
        ]);
        $requestB = new ServerRequest([
            'url' => '/credentials/index',
            'params' => ['controller' => 'Credentials', 'action' => 'view'],
        ]);
        Router::setRequest($requestA);

        $result = new Result(true);

        $watcher = new AuthorizationWatcher(['link_checks' => 'off']);

        $watcher->recordFromSpeculumEvent(new Event('Speculum.Authorization.checked', null, [
            'allowed' => true,
            'user' => ['id' => 1],
            'request' => $requestA,
            'result' => $result,
            'policy_class' => null,
        ]));

        $watcher->recordFromSpeculumEvent(new Event('Speculum.Authorization.checked', null, [
            'allowed' => true,
            'user' => ['id' => 1],
            'request' => $requestB,
            'result' => $result,
            'policy_class' => null,
        ]));

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(2, $entries);
        $abilities = array_map(fn($e): mixed => $e->content['ability'], $entries);
        $this->assertContains('Credentials/index', $abilities);
        $this->assertContains('Credentials/view', $abilities);
    }

    /**
     * RBAC and decorator both record for the same ability — no cross-dedup.
     *
     * @return void
     */
    public function testRbacAndDecoratorBothRecordForSameAbility(): void
    {
        $request = new ServerRequest([
            'url' => '/credentials',
            'params' => ['controller' => 'Credentials', 'action' => 'index'],
        ]);
        Router::setRequest($request);

        $watcher = new AuthorizationWatcher(['link_checks' => 'off']);

        $watcher->recordFromEvent(new Event('Auth.Rbac.checked', null, [
            'allowed' => true,
            'role' => 'admin',
            'user' => ['id' => 1],
            'request' => $request,
            'permission' => ['controller' => 'Credentials', 'action' => ['index']],
            'reason' => null,
        ]));

        $watcher->recordFromSpeculumEvent(new Event('Speculum.Authorization.checked', null, [
            'allowed' => true,
            'user' => ['id' => 1],
            'request' => $request,
            'result' => new Result(true),
            'policy_class' => 'CakeDC\\Auth\\Policy\\CollectionPolicy',
        ]));

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(2, $entries);

        $types = array_map(fn($e) => $e->type, $entries);
        $this->assertContains(EntryType::CakeDCAuth->value, $types);
        $this->assertContains(EntryType::Authorization->value, $types);

        foreach ($entries as $entry) {
            $this->assertSame('Credentials/index', $entry->content['ability']);
        }

        $rbacEntry = array_values(array_filter($entries, fn($e): bool => $e->type === EntryType::CakeDCAuth->value))[0];
        $authEntry = array_values(array_filter($entries, fn($e): bool => $e->type === EntryType::Authorization->value))[0];
        $this->assertSame('admin', $rbacEntry->content['role']);
        $this->assertSame('CakeDC\\Auth\\Policy\\CollectionPolicy', $authEntry->content['policy']);
    }

    /**
     * Link check dedup prevents duplicate entries for the same request target.
     *
     * @return void
     */
    public function testLinkCheckDedupByTarget(): void
    {
        $active = new ServerRequest(['url' => '/dashboard']);
        Router::setRequest($active);

        $linkRequest = new ServerRequest([
            'url' => '/users/edit/1',
            'params' => ['controller' => 'Users', 'action' => 'edit'],
        ]);

        $watcher = new AuthorizationWatcher(['link_checks' => 'denies']);

        $watcher->record([
            'allowed' => false,
            'role' => 'user',
            'user' => ['id' => 1],
            'request' => $linkRequest,
            'permission' => null,
            'reason' => 'denied',
        ]);

        $watcher->record([
            'allowed' => false,
            'role' => 'user',
            'user' => ['id' => 1],
            'request' => $linkRequest,
            'permission' => null,
            'reason' => 'denied again',
        ]);

        $entries = $this->loadSpeculumEntries();
        $this->assertCount(1, $entries);
    }
}
