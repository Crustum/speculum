<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Watcher;

use Cake\View\View;
use Crustum\Speculum\Enum\EntryType;
use Crustum\Speculum\Test\TestCase\TestCaseBase;
use Crustum\Speculum\Watcher\ViewWatcher;

/**
 * View watcher tests (PHP templates).
 */
class ViewWatcherTest extends TestCaseBase
{
    /**
     * @return void
     */
    public function testViewWatcherRegistersViews(): void
    {
        $watcher = new ViewWatcher(['enabled' => true]);
        $view = new View();
        $view->set('items', [1, 2, 3]);
        $view->setTemplate('welcome');

        $watcher->record($view, '/path/to/welcome.php');

        $entries = $this->loadSpeculumEntries();
        $entry = $entries[0];

        $this->assertSame(EntryType::View->value, $entry->type);
        $this->assertSame('welcome', $entry->content['name']);
        $this->assertSame('/path/to/welcome.php', $entry->content['path']);
        $this->assertSame(['items'], $entry->content['data']);
    }
}
