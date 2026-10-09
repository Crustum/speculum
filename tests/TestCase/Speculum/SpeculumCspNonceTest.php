<?php
declare(strict_types=1);

namespace Crustum\Speculum\Test\TestCase\Speculum;

use Cake\Http\ServerRequest;
use Cake\View\View;
use Crustum\Speculum\Speculum;
use Crustum\Speculum\Test\TestCase\TestCaseBase;

/**
 * CSP nonce coverage for the Speculum dashboard layout.
 */
class SpeculumCspNonceTest extends TestCaseBase
{
    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        Speculum::$cspNonce = '';

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testLayoutRendersWithoutNonceByDefault(): void
    {
        $html = $this->renderLayout();

        $this->assertStringContainsString('<style>', $html);
        $this->assertStringContainsString('<script>', $html);
        $this->assertStringNotContainsString('nonce=', $html);
    }

    /**
     * @return void
     */
    public function testStaticSetterRendersNonceOnAllTags(): void
    {
        Speculum::cspNonce('static-nonce-123');

        $html = $this->renderLayout();

        $this->assertStringContainsString('<style nonce="static-nonce-123">', $html);
        $this->assertStringContainsString('<script nonce="static-nonce-123">', $html);
        $this->assertSame(4, substr_count($html, 'nonce="static-nonce-123"'));
    }

    /**
     * @return void
     */
    public function testRequestAttributeWinsOverStaticSetter(): void
    {
        Speculum::cspNonce('static-nonce');

        $html = $this->renderLayout('attribute-nonce');

        $this->assertStringContainsString('nonce="attribute-nonce"', $html);
        $this->assertStringNotContainsString('static-nonce', $html);
    }

    /**
     * @return void
     */
    public function testNonceValueIsEscaped(): void
    {
        Speculum::cspNonce('"><script>alert(1)</script>');

        $html = $this->renderLayout();

        $this->assertStringNotContainsString('"><script>alert(1)</script>', $html);
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    /**
     * Render the dashboard layout with an optional request nonce attribute.
     *
     * @param string|null $requestNonce Nonce for the `cspNonce` request attribute.
     * @return string
     */
    protected function renderLayout(?string $requestNonce = null): string
    {
        $request = new ServerRequest(['url' => '/speculum']);
        if ($requestNonce !== null) {
            $request = $request->withAttribute('cspNonce', $requestNonce);
        }

        $view = new View($request, null, null, ['plugin' => 'Crustum/Speculum']);
        $view->set(['speculumScript' => [
            'path' => 'speculum',
            'timezone' => 'UTC',
            'recording' => true,
        ]]);

        return $view->renderLayout('', 'speculum');
    }
}
