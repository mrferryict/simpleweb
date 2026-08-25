<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\BaseController;
use App\Services\Media\MediaService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Controlled document download (ADR-007 / ADR-018).
 *
 * GET /download/document/{download_token}
 */
class DocumentDownloadController extends BaseController
{
    public function document(string $token): ResponseInterface
    {
        $resolved = $this->mediaService()->resolveDocumentDownload($token);
        if ($resolved === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($resolved['path'], null)->setFileName($resolved['filename']);
    }

    private function mediaService(): MediaService
    {
        return service('mediaService');
    }
}
