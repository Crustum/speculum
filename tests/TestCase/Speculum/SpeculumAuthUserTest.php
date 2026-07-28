<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Speculum;

use Crustum\Speculum\Entry\IncomingEntry;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use stdClass;

/**
 * Auth identity tagging on recorded entries.
 */
class SpeculumAuthUserTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testRecordAttachesAuthTagAndUserContent(): void
    {
        $identity = new stdClass();
        $identity->id = 'user-42';
        $identity->username = 'evgeny';
        $identity->email = 'evgeny@example.com';
        Speculum::auth($identity);

        Speculum::recordEntry(EntryType::Request, IncomingEntry::make([
            'uri' => '/posts',
            'method' => 'GET',
            'response_status' => 200,
        ]));

        $this->assertCount(1, Speculum::$entriesQueue);
        $entry = Speculum::$entriesQueue[0];
        $this->assertContains('Auth:user-42', $entry->tags);
        $this->assertSame('user-42', $entry->content['user']['id']);
        $this->assertSame('evgeny', $entry->content['user']['name']);
        $this->assertSame('evgeny@example.com', $entry->content['user']['email']);
    }

    /**
     * @return void
     */
    public function testRecordWithoutAuthOmitsUser(): void
    {
        Speculum::recordEntry(EntryType::Request, IncomingEntry::make([
            'uri' => '/posts',
            'method' => 'GET',
            'response_status' => 200,
        ]));

        $entry = Speculum::$entriesQueue[0];
        $this->assertSame([], $entry->tags);
        $this->assertArrayNotHasKey('user', $entry->content);
    }

    /**
     * @return void
     */
    public function testUserUsesGetIdentifierWhenPresent(): void
    {
        $identity = new class {
            public string $email = 'a@b.c';

            public string $username = 'bob';

            /**
             * @return string
             */
            public function getIdentifier(): string
            {
                return 'id-99';
            }
        };

        $entry = IncomingEntry::make([])->user($identity);
        $this->assertContains('Auth:id-99', $entry->tags);
        $this->assertSame('id-99', $entry->content['user']['id']);
        $this->assertSame('bob', $entry->content['user']['name']);
    }
}
