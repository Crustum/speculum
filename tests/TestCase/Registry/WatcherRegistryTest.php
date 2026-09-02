<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Registry;

use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Enum\SoftFeature;
use Crustum\Speculum\Registry\WatcherRegistry;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\AuthorizationWatcher;

/**
 * WatcherRegistry entry-resource registration (incl. path merge for renames).
 */
class WatcherRegistryTest extends TestCaseBase
{
    /**
     * Registering the same path twice must merge types/softs instead of overwriting,
     * so a renamed resource (e.g. cakedc_auth -> authorization) still surfaces both.
     *
     * @return void
     */
    public function testRegisterEntryResourceMergesTypesAndSoftsByPath(): void
    {
        $path = 'registry-merge-' . uniqid();

        WatcherRegistry::registerEntryResource(
            $path,
            AuthorizationWatcher::class,
            EntryType::Authorization->value,
            SoftFeature::Authorization,
        );
        WatcherRegistry::registerEntryResource(
            $path,
            AuthorizationWatcher::class,
            EntryType::CakeDCAuth->value,
            SoftFeature::CakeDCAuth,
        );

        $resource = WatcherRegistry::entryResource($path);
        $this->assertNotNull($resource);

        $this->assertIsArray($resource->type);
        $this->assertContains(EntryType::Authorization->value, $resource->type);
        $this->assertContains(EntryType::CakeDCAuth->value, $resource->type);

        $this->assertIsArray($resource->soft);
        $this->assertContains(SoftFeature::Authorization, $resource->soft);
        $this->assertContains(SoftFeature::CakeDCAuth, $resource->soft);
    }
}
