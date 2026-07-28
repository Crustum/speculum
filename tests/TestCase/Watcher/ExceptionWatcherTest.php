<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\Event\Event;
use Cake\View\View;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\Support\BananaError;
use Crustum\Speculum\Test\Support\BananaException;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\ExceptionWatcher;
use ErrorException;
use InvalidArgumentException;
use ParseError;
use RuntimeException;

/**
 * Exception watcher tests.
 */
class ExceptionWatcherTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testExceptionWatcherRegisterEntries(): void
    {
        $watcher = new ExceptionWatcher(['enabled' => true]);
        $exception = new BananaException('Something went bananas.');
        $watcher->recordException($exception);

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Exception->value, $entry->type);
        $this->assertSame(BananaException::class, $entry->content['class']);
        $this->assertSame(__FILE__, $entry->content['file']);
        $this->assertSame($exception->getLine(), $entry->content['line']);
        $this->assertSame('Something went bananas.', $entry->content['message']);
        $this->assertArrayHasKey('trace', $entry->content);
    }

    /**
     * @return void
     */
    public function testExceptionWatcherRegisterThrowableEntries(): void
    {
        $watcher = new ExceptionWatcher(['enabled' => true]);
        $exception = new BananaError('Something went bananas.');
        $watcher->recordException($exception);

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Exception->value, $entry->type);
        $this->assertSame(BananaError::class, $entry->content['class']);
        $this->assertSame(__FILE__, $entry->content['file']);
        $this->assertSame($exception->getLine(), $entry->content['line']);
        $this->assertSame('Something went bananas.', $entry->content['message']);
        $this->assertArrayHasKey('trace', $entry->content);
    }

    /**
     * @return void
     */
    public function testExceptionWatcherRegisterEntriesWhenEvalFailed(): void
    {
        $watcher = new ExceptionWatcher(['enabled' => true]);
        $exception = null;

        try {
            eval('if (');
            $this->fail('eval() was expected to throw a parse error');
        } catch (ParseError $parseError) {
            $exception = new ErrorException($parseError->getMessage(), $parseError->getCode(), 1, $parseError->getFile(), $parseError->getLine(), $parseError);
        }

        $watcher->recordException($exception);

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::Exception->value, $entry->type);
        $this->assertSame(ErrorException::class, $entry->content['class']);
        $this->assertStringContainsString("eval()'d code", (string)$entry->content['file']);
        $this->assertSame(1, $entry->content['line']);
        $this->assertSame("Unclosed '('", $entry->content['message']);
        $this->assertArrayHasKey('trace', $entry->content);
    }

    /**
     * @return void
     */
    public function testRecordsException(): void
    {
        $watcher = new ExceptionWatcher();
        $watcher->recordException(new InvalidArgumentException('watched'));

        $this->assertCount(1, Speculum::$entriesQueue);
        $this->assertSame(EntryType::Exception->value, Speculum::$entriesQueue[0]->type);
        $this->assertSame('watched', Speculum::$entriesQueue[0]->content['message']);
        $this->assertTrue(Speculum::$entriesQueue[0]->isException());
    }

    /**
     * @return void
     */
    public function testRecordsNonVendorLocationWhenThrownFromVendor(): void
    {
        $vendorDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'speculum_exception_watcher'
            . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'lib';
        if (!is_dir($vendorDir) && !mkdir($vendorDir, 0777, true) && !is_dir($vendorDir)) {
            $this->fail('Unable to create temporary vendor directory.');
        }

        $throwFile = $vendorDir . DIRECTORY_SEPARATOR . 'ThrowFromVendor.php';
        file_put_contents(
            $throwFile,
            "<?php\nthrow new RuntimeException('vendor-throw');\n",
        );

        $exception = null;
        $expectedLine = __LINE__ + 2;
        try {
            require $throwFile;
        } catch (RuntimeException $runtimeException) {
            $exception = $runtimeException;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);

        $watcher = new ExceptionWatcher(['enabled' => true]);
        $watcher->recordException($exception);

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(__FILE__, $entry->content['file']);
        $this->assertSame($expectedLine, $entry->content['line']);
        $this->assertNotSame($exception->getFile(), $entry->content['file']);
    }

    /**
     * @return void
     */
    public function testRecordsExceptionFromErrorView(): void
    {
        $watcher = new ExceptionWatcher(['enabled' => true]);
        $view = new View();
        $view->setTemplate('error500');
        $view->set('error', new RuntimeException('from-error-view'));

        $event = new Event('View.beforeRender', $view, ['error500']);
        $watcher->onViewBeforeRender($event, 'templates/Error/error500.php');

        $this->assertCount(1, Speculum::$entriesQueue);
        $this->assertSame('from-error-view', Speculum::$entriesQueue[0]->content['message']);
    }
}
