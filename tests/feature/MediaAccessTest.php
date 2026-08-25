<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Dtos\MediaUploadDto;
use App\Services\Media\MediaService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Media HTTP boundaries (Phase 4 / Task 4.5).
 *
 * @internal
 */
final class MediaAccessTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    /**
     * @var list<string>
     */
    protected $namespace = [
        'CodeIgniter\Settings',
        'App',
    ];

    protected $migrate = true;
    protected $refresh = true;

    public function testAdminMediaRequiresAuth(): void
    {
        $result = $this->get('admin/media');
        $result->assertRedirect();
    }

    public function testMediaUploadRequiresCsrfOrAuth(): void
    {
        try {
            $result = $this->post('admin/media', []);
            $status = $result->response()->getStatusCode();
            $this->assertTrue(in_array($status, [302, 303, 403], true));
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testDocumentDownloadInvalidTokenIs404(): void
    {
        try {
            $result = $this->get('download/document/ffffffffffffffffffffffffffffffff');
            $result->assertStatus(404);
        } catch (PageNotFoundException $e) {
            $this->assertSame(404, $e->getCode());
        }
    }

    public function testDocumentDownloadValidTokenStreams(): void
    {
        $media = Services::mediaService(getShared: false);
        $fixture = WRITEPATH . 'uploads/tmp/fixtures';
        if (! is_dir($fixture)) {
            mkdir($fixture, 0755, true);
        }
        $path = $fixture . '/http.pdf';
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");
        $result = $media->upload(new MediaUploadDto(
            tmpPath: $path,
            originalFilename: 'http.pdf',
            clientMime: 'application/pdf',
            sizeBytes: (int) filesize($path),
        ));
        $this->assertSame([], $result['errors']);
        $token = (string) $result['asset']->download_token;

        $response = $this->get('download/document/' . $token);
        $response->assertStatus(200);
    }

    public function testNewsAndPageRoutesRemainIntact(): void
    {
        $result = $this->get('admin/pages');
        $result->assertRedirect();
        $this->assertPublicNotFound('missing-page-media-xyz');
    }

    private function assertPublicNotFound(string $path): void
    {
        try {
            $result = $this->get($path);
            $result->assertStatus(404);
        } catch (PageNotFoundException $e) {
            $this->assertSame(404, $e->getCode());
        }
    }
}
