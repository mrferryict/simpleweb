<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Dtos\MediaUploadDto;
use App\Services\Media\MediaService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Media picker HTTP boundary (Phase 4 / Task 4.6).
 *
 * @internal
 */
final class MediaPickerAccessTest extends CIUnitTestCase
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

    public function testPickerRequiresAuthentication(): void
    {
        $result = $this->get('admin/media/picker?type=IMAGE');
        $result->assertRedirect();
    }

    public function testAdminPagesStillProtected(): void
    {
        $result = $this->get('admin/pages/new');
        $result->assertRedirect();
    }

    public function testPickerListHelpersExcludeTrashAndWrongType(): void
    {
        $media = Services::mediaService(getShared: false);
        $img   = $this->upload($media, 'p.png', true);
        $doc   = $this->upload($media, 'p.pdf', false);
        $this->assertSame([], $media->trash((int) $img['asset']->id));

        $images = $media->listActiveForPicker('IMAGE');
        $ids    = array_map(static fn ($a) => (int) $a->id, $images);
        $this->assertNotContains((int) $img['asset']->id, $ids);
        $this->assertNotContains((int) $doc['asset']->id, $ids);

        $docs = $media->listActiveForPicker('DOCUMENT');
        $docIds = array_map(static fn ($a) => (int) $a->id, $docs);
        $this->assertContains((int) $doc['asset']->id, $docIds);
        $this->assertNotContains((int) $img['asset']->id, $docIds);
    }

    /**
     * @return array{errors: array<string, string>, asset: \App\Entities\MediaAsset|null}
     */
    private function upload(MediaService $media, string $name, bool $image): array
    {
        $dir = WRITEPATH . 'uploads/tmp/fixtures';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/' . $name;
        if ($image) {
            $im = imagecreatetruecolor(20, 20);
            imagepng($im, $path);
            imagedestroy($im);
        } else {
            file_put_contents($path, "%PDF-1.4\n%%EOF\n");
        }

        return $media->upload(new MediaUploadDto(
            tmpPath: $path,
            originalFilename: $name,
            clientMime: $image ? 'image/png' : 'application/pdf',
            sizeBytes: (int) filesize($path),
        ));
    }
}
