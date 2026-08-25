<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Security as SecurityConfig;

/**
 * Emits the current session-backed CSRF hash on HTMX responses (DOC-03 §11.2).
 *
 * CI4 regenerates the hash during Security::verify() when regenerate=true, but
 * the stock CSRF filter does not expose the new value to the client. HTMX clients
 * read this response header and update the DOM token source for subsequent requests.
 *
 * Does not implement a second CSRF algorithm — only surfaces csrf_hash().
 */
class CsrfTokenHeaderFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null): void
    {
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ResponseInterface
    {
        if (! $request instanceof IncomingRequest) {
            return $response;
        }

        if (strtolower($request->getHeaderLine('HX-Request')) !== 'true') {
            return $response;
        }

        /** @var SecurityConfig $config */
        $config = config(SecurityConfig::class);
        $hash   = csrf_hash();

        if (is_string($hash) && $hash !== '') {
            $response->setHeader($config->headerName, $hash);
        }

        return $response;
    }
}
