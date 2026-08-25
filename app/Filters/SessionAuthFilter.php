<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Filters\SessionAuth;

/**
 * Project SessionAuth wrapper (DOC-03 §10.3).
 *
 * Delegates authentication to Shield SessionAuth. For HTMX requests that
 * fail authentication / lose session, returns HX-Redirect: /cp so the
 * login page is never swapped into an HTMX fragment.
 *
 * Authorization denials (GroupFilter) are intentionally not handled here.
 */
class SessionAuthFilter implements FilterInterface
{
    private SessionAuth $sessionAuth;

    public function __construct(?SessionAuth $sessionAuth = null)
    {
        $this->sessionAuth = $sessionAuth ?? new SessionAuth();
    }

    /**
     * @param list<string>|null $arguments
     *
     * @return RedirectResponse|ResponseInterface|void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $result = $this->sessionAuth->before($request, $arguments);

        if ($result === null) {
            return;
        }

        if (! $request instanceof IncomingRequest) {
            return $result;
        }

        if (! $this->isHtmxRequest($request)) {
            return $result;
        }

        if (! $result instanceof RedirectResponse) {
            return $result;
        }

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        // Preserve pending auth-action redirects (e.g. 2FA); those are not session expiry.
        if ($authenticator->isPending()) {
            return $result;
        }

        // Authentication / session-loss failure: full-window navigate to /cp.
        return $this->htmxRedirectToCp();
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        $this->sessionAuth->after($request, $response, $arguments);
    }

    private function isHtmxRequest(IncomingRequest $request): bool
    {
        return strtolower($request->getHeaderLine('HX-Request')) === 'true';
    }

    private function htmxRedirectToCp(): ResponseInterface
    {
        return service('response')
            ->setStatusCode(200)
            ->setHeader('HX-Redirect', '/cp')
            ->setBody('');
    }
}
