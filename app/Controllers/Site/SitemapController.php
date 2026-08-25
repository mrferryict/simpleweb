<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\BaseController;
use App\Services\Localization\SitemapService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Public sitemap.xml (REQ-SEO-003 / ADR-024).
 */
class SitemapController extends BaseController
{
    public function index(): ResponseInterface
    {
        $xml = $this->sitemapService()->toXml();

        return $this->response
            ->setStatusCode(200)
            ->setContentType('application/xml')
            ->setBody($xml);
    }

    private function sitemapService(): SitemapService
    {
        return service('sitemapService');
    }
}
