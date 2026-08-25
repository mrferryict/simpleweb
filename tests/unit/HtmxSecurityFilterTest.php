<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Filters\CsrfTokenHeaderFilter;
use App\Filters\SessionAuthFilter;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\URI;
use CodeIgniter\Shield\Filters\SessionAuth;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Filters as FiltersConfig;
use Config\Security as SecurityConfig;

/**
 * HTMX CSRF sync + session expiry filter contracts (Phase 3 / Task 3.6).
 *
 * @internal
 */
final class HtmxSecurityFilterTest extends CIUnitTestCase
{
    public function testCsrfIsSessionBacked(): void
    {
        /** @var SecurityConfig $security */
        $security = config(SecurityConfig::class);
        $this->assertSame('session', $security->csrfProtection);
        $this->assertSame('X-CSRF-TOKEN', $security->headerName);
        $this->assertTrue($security->regenerate);
    }

    public function testLogoutIsOnlyCsrfException(): void
    {
        /** @var FiltersConfig $filters */
        $filters = config(FiltersConfig::class);
        $csrf    = $filters->globals['before']['csrf'] ?? null;
        $this->assertIsArray($csrf);
        $this->assertSame(['logout'], $csrf['except']);
    }

    public function testSecureHeadersIsEnabledInGlobalAfterFilters(): void
    {
        /** @var FiltersConfig $filters */
        $filters = config(FiltersConfig::class);
        $this->assertContains('secureheaders', $filters->globals['after']);
    }

    public function testSessionAuthFilterNormalUnauthenticatedRedirects(): void
    {
        $redirect = new RedirectResponse(new App());
        $redirect->redirect('/cp');

        $shield = $this->createMock(SessionAuth::class);
        $shield->expects($this->once())
            ->method('before')
            ->willReturn($redirect);

        $filter  = new SessionAuthFilter($shield);
        $request = $this->makeRequest(false);
        $result  = $filter->before($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testSessionAuthFilterHtmxUnauthenticatedReturnsHxRedirect(): void
    {
        $redirect = new RedirectResponse(new App());
        $redirect->redirect('/cp');

        $shield = $this->createMock(SessionAuth::class);
        $shield->expects($this->once())
            ->method('before')
            ->willReturn($redirect);

        $filter  = new SessionAuthFilter($shield);
        $request = $this->makeRequest(true);
        $result  = $filter->before($request);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertNotInstanceOf(RedirectResponse::class, $result);
        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame('/cp', $result->getHeaderLine('HX-Redirect'));
        $this->assertSame('', $result->getBody());
    }

    public function testCsrfTokenHeaderFilterSetsHeaderOnHtmxResponse(): void
    {
        $filter   = new CsrfTokenHeaderFilter();
        $request  = $this->makeRequest(true);
        $response = service('response');
        $hash     = csrf_hash();

        $out = $filter->after($request, $response);
        $this->assertSame($hash, $out->getHeaderLine('X-CSRF-TOKEN'));
    }

    public function testCsrfTokenHeaderFilterSkipsNonHtmxResponse(): void
    {
        $filter   = new CsrfTokenHeaderFilter();
        $request  = $this->makeRequest(false);
        $response = service('response');
        $response->removeHeader('X-CSRF-TOKEN');

        $out = $filter->after($request, $response);
        $this->assertSame('', $out->getHeaderLine('X-CSRF-TOKEN'));
    }

    public function testCsrfTokenHeaderFilterIsRegisteredGloballyAfter(): void
    {
        /** @var FiltersConfig $filters */
        $filters = config(FiltersConfig::class);
        $this->assertContains('csrfTokenHeader', $filters->globals['after']);
        $this->assertSame(CsrfTokenHeaderFilter::class, $filters->aliases['csrfTokenHeader']);
    }

    private function makeRequest(bool $htmx): IncomingRequest
    {
        $request = new IncomingRequest(
            new App(),
            new URI('https://example.test/admin'),
            null,
            new \CodeIgniter\HTTP\UserAgent(),
        );
        if ($htmx) {
            $request->setHeader('HX-Request', 'true');
        }

        return $request;
    }
}
