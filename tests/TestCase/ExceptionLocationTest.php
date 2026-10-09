<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase;

use Crustum\Speculum\Support\ExceptionLocation;
use ErrorException;
use RuntimeException;

/**
 * Exception location attribution tests.
 */
class ExceptionLocationTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testKeepsNonVendorThrowSite(): void
    {
        $exception = new RuntimeException('app');
        $location = ExceptionLocation::fromThrowable($exception);

        $this->assertSame(__FILE__, $location['file']);
        $this->assertSame($exception->getLine(), $location['line']);
    }

    /**
     * @return void
     */
    public function testPrefersFirstNonVendorStackFrame(): void
    {
        $vendorDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'speculum_exception_loc'
            . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'lib';
        if (!is_dir($vendorDir) && !mkdir($vendorDir, 0777, true) && !is_dir($vendorDir)) {
            $this->fail('Unable to create temporary vendor directory.');
        }

        $throwFile = $vendorDir . DIRECTORY_SEPARATOR . 'ThrowFromVendor.php';
        file_put_contents(
            $throwFile,
            "<?php\nthrow new RuntimeException('from-vendor');\n",
        );

        $exception = null;
        $expectedLine = __LINE__ + 2;
        try {
            require $throwFile;
        } catch (RuntimeException $runtimeException) {
            $exception = $runtimeException;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertTrue(ExceptionLocation::isVendorPath($exception->getFile()));

        $location = ExceptionLocation::fromThrowable($exception);
        $this->assertSame(__FILE__, $location['file']);
        $this->assertSame($expectedLine, $location['line']);
    }

    /**
     * @return void
     */
    public function testFallsBackToVendorThrowSiteWhenStackIsVendorOnly(): void
    {
        $location = ExceptionLocation::resolve(
            '/app/vendor/cakephp/cakephp/src/Database/Driver.php',
            391,
            [
                ['file' => '/app/vendor/cakephp/cakephp/src/Database/Connection.php', 'line' => 317],
                ['file' => '/app/vendor/cakephp/cakephp/src/ORM/Query/SelectQuery.php', 'line' => 389],
            ],
        );

        $this->assertSame('/app/vendor/cakephp/cakephp/src/Database/Driver.php', $location['file']);
        $this->assertSame(391, $location['line']);
    }

    /**
     * @return void
     */
    public function testKeepsEvalLocation(): void
    {
        $exception = new ErrorException('eval', 0, E_ERROR, "eval()'d code", 4);
        $location = ExceptionLocation::fromThrowable($exception);

        $this->assertSame("eval()'d code", $location['file']);
        $this->assertSame(4, $location['line']);
    }

    /**
     * @return void
     */
    public function testIsVendorPathNormalizesSeparators(): void
    {
        $this->assertTrue(ExceptionLocation::isVendorPath('C:\\app\\vendor\\cake\\Driver.php'));
        $this->assertTrue(ExceptionLocation::isVendorPath('/app/vendor/cake/Driver.php'));
    }
}
