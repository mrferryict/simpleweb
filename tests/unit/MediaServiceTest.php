<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\MediaUploadDto;
use App\Dtos\PageWriteDto;
use App\Dtos\PostWriteDto;
use App\Enums\MediaType;
use App\Services\Media\MediaService;
use App\Services\PostService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * Media Library foundation (Phase 4 / Task 4.5 / ADR-018).
 *
 * @internal
 */
final class MediaServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    /**
     * @var list<string>
     */
    protected $namespace = [
        'CodeIgniter\Settings',
        'App',
    ];

    protected $migrate = true;
    protected $refresh = true;

    private MediaService $media;
    private string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->media      = Services::mediaService(getShared: false);
        $this->fixtureDir = WRITEPATH . 'uploads/tmp/fixtures';
        if (! is_dir($this->fixtureDir)) {
            mkdir($this->fixtureDir, 0755, true);
        }
    }

    public function testMediaAssetsTableExistsWithExpectedColumns(): void
    {
        $this->assertTrue(db_connect()->tableExists('media_assets'));
        $fields = db_connect()->getFieldNames('media_assets');
        foreach (
            [
                'id', 'type', 'title', 'description', 'alt', 'original_filename', 'storage_key',
                'mime_type', 'extension', 'file_size', 'width', 'height', 'download_token',
                'status', 'uploaded_by', 'created_at', 'updated_at', 'deleted_at',
            ] as $col
        ) {
            $this->assertContains($col, $fields);
        }
    }

    public function testUploadValidPngImage(): void
    {
        $path = $this->makePngFixture('photo.png', 80, 60);
        $result = $this->media->upload($this->uploadDto($path, 'photo.png'), $this->userWith(['media.upload']));
        $this->assertSame([], $result['errors']);
        $this->assertNotNull($result['asset']);
        $this->assertSame(MediaType::Image->value, $result['asset']->type);
        $this->assertNull($result['asset']->download_token);
        $this->assertSame('photo.png', $result['asset']->original_filename);
        $this->assertNotSame('photo.png', $result['asset']->storage_key);
        $this->assertFileExists($this->media->absolutePathFor($result['asset']));
        $url = $this->media->publicImageUrl((int) $result['asset']->id);
        $this->assertNotNull($url);
        $this->assertStringStartsWith('/uploads/images/', $url);
    }

    public function testUploadValidPdfDocument(): void
    {
        $path = $this->makePdfFixture('brochure.pdf');
        $result = $this->media->upload($this->uploadDto($path, 'brochure.pdf'), $this->userWith(['media.upload']));
        $this->assertSame([], $result['errors']);
        $this->assertNotNull($result['asset']);
        $this->assertSame(MediaType::Document->value, $result['asset']->type);
        $this->assertNotNull($result['asset']->download_token);
        $this->assertSame(32, strlen((string) $result['asset']->download_token));
        $resolved = $this->media->resolveDocumentDownload((string) $result['asset']->download_token);
        $this->assertNotNull($resolved);
    }

    public function testSvgIsRejected(): void
    {
        $path = $this->fixtureDir . '/evil.svg';
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $result = $this->media->upload($this->uploadDto($path, 'evil.svg'));
        $this->assertArrayHasKey('file', $result['errors']);
        $this->assertNull($result['asset']);
    }

    public function testOversizedImageRejected(): void
    {
        $path = $this->makePngFixture('big.png', 10, 10);
        $dto  = new MediaUploadDto(
            tmpPath: $path,
            originalFilename: 'big.png',
            clientMime: 'image/png',
            sizeBytes: 5 * 1024 * 1024 + 1,
        );
        $result = $this->media->upload($dto);
        $this->assertArrayHasKey('file', $result['errors']);
    }

    public function testDuplicateOriginalFilenamesRemainDistinct(): void
    {
        $a = $this->media->upload($this->uploadDto($this->makePngFixture('a.png', 20, 20), 'same.png'));
        $b = $this->media->upload($this->uploadDto($this->makePngFixture('b.png', 22, 22), 'same.png'));
        $this->assertSame([], $a['errors']);
        $this->assertSame([], $b['errors']);
        $this->assertNotSame($a['asset']->storage_key, $b['asset']->storage_key);
        $this->assertSame('same.png', $a['asset']->original_filename);
        $this->assertSame('same.png', $b['asset']->original_filename);
    }

    public function testTrashRestoreAndPublicResolution(): void
    {
        $result = $this->media->upload($this->uploadDto($this->makePngFixture('t.png', 30, 30), 't.png'));
        $id     = (int) $result['asset']->id;
        $this->assertSame([], $this->media->trash($id));
        $this->assertNull($this->media->publicImageUrl($id));
        $this->assertSame([], $this->media->restore($id));
        $this->assertNotNull($this->media->publicImageUrl($id));
    }

    public function testTrashDocumentNotDownloadable(): void
    {
        $result = $this->media->upload($this->uploadDto($this->makePdfFixture('d.pdf'), 'd.pdf'));
        $token  = (string) $result['asset']->download_token;
        $this->assertSame([], $this->media->trash((int) $result['asset']->id));
        $this->assertNull($this->media->resolveDocumentDownload($token));
    }

    public function testImageCannotUseDocumentDownload(): void
    {
        $result = $this->media->upload($this->uploadDto($this->makePngFixture('i.png', 12, 12), 'i.png'));
        $this->assertNull($this->media->resolveDocumentDownload('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'));
        unset($result);
    }

    public function testPermanentDeleteBlockedByFeaturedImage(): void
    {
        $img = $this->media->upload($this->uploadDto($this->makePngFixture('f.png', 40, 40), 'f.png'));
        $id  = (int) $img['asset']->id;
        $posts = Services::postService(getShared: false);
        $this->assertSame([], $posts->create(new PostWriteDto(
            title: 'P',
            slug: 'feat-post',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: [],
        )));
        $postId = $posts->listActive()[0]['post']->id;
        db_connect()->table('posts')->where('id', $postId)->update(['featured_image_id' => $id]);

        $admin = $this->userWith(['content.permanent_delete']);
        $errors = $this->media->permanentlyDelete($id, $admin);
        $this->assertArrayHasKey('_dependency', $errors);
    }

    public function testPermanentDeleteBlockedByPagePayload(): void
    {
        $img = $this->media->upload($this->uploadDto($this->makePngFixture('p.png', 40, 40), 'p.png'));
        $id  = (int) $img['asset']->id;
        $pages = Services::pageService(getShared: false);
        $this->assertSame([], $pages->create(new PageWriteDto(
            title: 'About',
            slug: 'about-media',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['hero_image' => $id],
        )));

        $errors = $this->media->permanentlyDelete($id, $this->userWith(['content.permanent_delete']));
        $this->assertArrayHasKey('_dependency', $errors);
    }

    public function testPermanentDeleteSucceedsWhenUnreferenced(): void
    {
        $img = $this->media->upload($this->uploadDto($this->makePngFixture('u.png', 16, 16), 'u.png'));
        $id  = (int) $img['asset']->id;
        $path = $this->media->absolutePathFor($img['asset']);
        $this->assertSame([], $this->media->permanentlyDelete($id, $this->userWith(['content.permanent_delete'])));
        $this->assertNull($this->media->findById($id));
        $this->assertFileDoesNotExist($path);
    }

    public function testContentSchemaResolverRejectsTrashAndWrongType(): void
    {
        $img = $this->media->upload($this->uploadDto($this->makePngFixture('c.png', 18, 18), 'c.png'));
        $doc = $this->media->upload($this->uploadDto($this->makePdfFixture('c.pdf'), 'c.pdf'));
        $imgId = (int) $img['asset']->id;
        $docId = (int) $doc['asset']->id;

        $validator = Services::contentSchemaValidator(getShared: false);
        $schema = [
            'hero_image' => ['type' => 'IMAGE', 'required' => true],
            'brochure'   => ['type' => 'DOCUMENT', 'required' => true],
        ];

        $ok = $validator->validate(['hero_image' => $imgId, 'brochure' => $docId], $schema);
        $this->assertTrue($ok->ok);

        $this->assertSame([], $this->media->trash($imgId));
        $bad = $validator->validate(['hero_image' => $imgId, 'brochure' => $docId], $schema);
        $this->assertFalse($bad->ok);

        $wrong = $validator->validate(['hero_image' => $docId, 'brochure' => $docId], $schema);
        $this->assertFalse($wrong->ok);
    }

    public function testPhpUploadRejected(): void
    {
        $path = $this->fixtureDir . '/shell.php';
        file_put_contents($path, '<?php echo 1;');
        $result = $this->media->upload($this->uploadDto($path, 'shell.php'));
        $this->assertArrayHasKey('file', $result['errors']);
    }

    public function testResolvePublicReferenceActiveImage(): void
    {
        $img = $this->media->upload($this->uploadDto($this->makePngFixture('pub.png', 24, 24), 'pub.png'));
        $id  = (int) $img['asset']->id;
        $ref = $this->media->resolvePublicReference($id, 'IMAGE');

        $this->assertNotNull($ref);
        $this->assertSame($id, $ref['media_id']);
        $this->assertSame('IMAGE', $ref['type']);
        $this->assertStringStartsWith('/uploads/images/', $ref['url']);
        $this->assertDoesNotMatchRegularExpression('#/uploads/images/' . $id . '$#', $ref['url']);
        $this->assertStringNotContainsString(WRITEPATH, $ref['url']);
        $this->assertStringNotContainsString(FCPATH, $ref['url']);
        $this->assertArrayNotHasKey('storage_key', $ref);
    }

    public function testResolvePublicReferenceTrashAndMissingImage(): void
    {
        $img = $this->media->upload($this->uploadDto($this->makePngFixture('gone.png', 20, 20), 'gone.png'));
        $id  = (int) $img['asset']->id;
        $this->assertSame([], $this->media->trash($id));

        $this->assertNull($this->media->resolvePublicReference($id, 'IMAGE'));
        $this->assertNull($this->media->publicImageUrl($id));
        $this->assertNull($this->media->resolvePublicReference(999999, 'IMAGE'));
    }

    public function testResolvePublicReferenceActiveDocument(): void
    {
        $doc = $this->media->upload($this->uploadDto($this->makePdfFixture('pub.pdf'), 'pub.pdf'));
        $id  = (int) $doc['asset']->id;
        $ref = $this->media->resolvePublicReference($id, 'DOCUMENT');

        $this->assertNotNull($ref);
        $this->assertSame($id, $ref['media_id']);
        $this->assertSame('DOCUMENT', $ref['type']);
        $this->assertStringStartsWith('/download/document/', $ref['url']);
        $this->assertSame(32, strlen(substr($ref['url'], strlen('/download/document/'))));
        $this->assertStringNotContainsString(WRITEPATH, $ref['url']);
        $this->assertStringNotContainsString('uploads/documents', $ref['url']);
        $this->assertArrayNotHasKey('storage_key', $ref);
    }

    public function testResolvePublicReferenceTrashAndMissingDocument(): void
    {
        $doc = $this->media->upload($this->uploadDto($this->makePdfFixture('gone.pdf'), 'gone.pdf'));
        $id  = (int) $doc['asset']->id;
        $this->assertSame([], $this->media->trash($id));

        $this->assertNull($this->media->resolvePublicReference($id, 'DOCUMENT'));
        $this->assertNull($this->media->publicDocumentUrl($id));
        $this->assertNull($this->media->resolvePublicReference(999999, 'DOCUMENT'));
    }

    public function testResolveContentMediaForSchemaPreservesPayloadAuthority(): void
    {
        $img = $this->media->upload($this->uploadDto($this->makePngFixture('schema.png', 16, 16), 'schema.png'));
        $doc = $this->media->upload($this->uploadDto($this->makePdfFixture('schema.pdf'), 'schema.pdf'));
        $imgId = (int) $img['asset']->id;
        $docId = (int) $doc['asset']->id;

        $payload = [
            'hero_image' => $imgId,
            'attachment' => $docId,
            'body'       => '<p>Keep</p>',
        ];
        $schema = [
            'hero_image' => ['type' => 'IMAGE', 'required' => false],
            'attachment' => ['type' => 'DOCUMENT', 'required' => false],
            'body'       => ['type' => 'RICH_TEXT', 'required' => false],
        ];

        $media = $this->media->resolveContentMediaForSchema($payload, $schema);
        $this->assertSame($imgId, $payload['hero_image']);
        $this->assertSame($docId, $payload['attachment']);
        $this->assertSame('<p>Keep</p>', $payload['body']);
        $this->assertArrayNotHasKey('body', $media);
        $this->assertNotNull($media['hero_image']);
        $this->assertNotNull($media['attachment']);
        $this->assertStringStartsWith('/uploads/images/', $media['hero_image']['url']);
        $this->assertStringStartsWith('/download/document/', $media['attachment']['url']);
    }

    public function testImageUploadUsesCmsDefaultProfileDimensions(): void
    {
        $theme = Services::themeService(getShared: false);
        $profile = $theme->resolveImageProfile('cms_default');
        $this->assertSame(2560, $profile['maximum_width']);

        $path = $this->makePngFixture('profile-ok.png', 80, 60);
        $result = $this->media->upload($this->uploadDto($path, 'profile-ok.png'));
        $this->assertSame([], $result['errors']);
        $this->assertNotNull($result['asset']);
        $this->assertLessThanOrEqual(2560, (int) $result['asset']->width);
        $this->assertLessThanOrEqual(2560, (int) $result['asset']->height);
    }

    /**
     * @param list<string> $permissions
     */
    private function userWith(array $permissions): User
    {
        $user = $this->createMock(User::class);
        $user->method('can')->willReturnCallback(
            static fn (string $p): bool => in_array($p, $permissions, true),
        );
        $user->id = 1;

        return $user;
    }

    private function uploadDto(string $path, string $name): MediaUploadDto
    {
        return new MediaUploadDto(
            tmpPath: $path,
            originalFilename: $name,
            clientMime: 'application/octet-stream',
            sizeBytes: (int) filesize($path),
        );
    }

    private function makePngFixture(string $name, int $w, int $h): string
    {
        $path = $this->fixtureDir . '/' . $name;
        $im   = imagecreatetruecolor($w, $h);
        $bg   = imagecolorallocate($im, 10, 20, 30);
        imagefill($im, 0, 0, $bg);
        imagepng($im, $path);
        imagedestroy($im);

        return $path;
    }

    private function makePdfFixture(string $name): string
    {
        $path = $this->fixtureDir . '/' . $name;
        file_put_contents($path, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");

        return $path;
    }
}
