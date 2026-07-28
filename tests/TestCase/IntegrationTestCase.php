<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Storage\DatabaseEntriesRepository;
use TestApp\Application;

/**
 * Base for HTTP integration tests against Crustum/Speculum routes.
 */
abstract class IntegrationTestCase extends TestCase
{
    use IntegrationTestTrait;

    /**
     * @var \Crustum\Speculum\Storage\DatabaseEntriesRepository
     */
    protected DatabaseEntriesRepository $repository;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->configApplication(Application::class, [CONFIG]);
        $this->repository = new DatabaseEntriesRepository('test', 100);
        Speculum::setRepository($this->repository);
        Speculum::$entriesQueue = [];
        Speculum::$updatesQueue = [];
        Speculum::$filterUsing = [];
        Speculum::$filterBatchUsing = [];
        Speculum::$tagUsing = [];
        Speculum::$afterStoringHooks = [];
        Speculum::$afterRecordingHook = null;
        Speculum::startRecording(false);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->repository->clear();
        Speculum::stopRecording();
        parent::tearDown();
    }

    /**
     * Decode JSON response body.
     *
     * @return array<string, mixed>
     */
    protected function jsonBody(): array
    {
        $this->assertNotNull($this->_response);
        $decoded = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
