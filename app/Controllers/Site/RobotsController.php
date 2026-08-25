<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\BaseController;
use App\Services\Localization\RobotsService;
use App\Services\Localization\SitemapService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Public robots.txt (REQ-SEO-004 / ADR-024).
 */
class RobotsController extends BaseController
{
    public function index(): ResponseInterface
    {
        $body = $this->robotsService()->toText();

        return $this->response
            ->setStatusCode(200)
            ->setContentType('text/plain')
            ->setBody($body);
    }

    private function robotsService(): RobotsService
    {
        return service('robotsService');
    }
}
