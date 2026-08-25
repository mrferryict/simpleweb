<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\MediaUploadDto;
use App\Services\Media\MediaService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * Media Picker field + list fragment (Phase 4 / Task 4.6).
 *
 * @internal
 */
final class MediaPickerViewTest extends CIUnitTestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->media = Services::mediaService(getShared: false);
    }

    public function testImageFieldRendersPickerWithBackingMediaId(): void
    {
        $img = $this->uploadPng('pick.png');
        $html = $this->renderFields(['hero_image' => (string) $img['asset']->id]);

        $this->assertStringContainsString('name="content[hero_image]"', $html);
        $this->assertStringContainsString('class="media-picker"', $html);
        $this->assertStringContainsString('data-media-type="IMAGE"', $html);
        $this->assertStringContainsString('x-data="mediaPicker(', $html);
        $this->assertStringContainsString('Select Media', $html);
        $this->assertStringContainsString('Clear', $html);
        $this->assertStringContainsString('media-picker.js', $html);
        $this->assertStringContainsString('/uploads/images/', $html);
        $this->assertStringNotContainsString('placeholder="media_id"', $html);
        $this->assertStringNotContainsString(WRITEPATH, $html);
        $this->assertStringNotContainsString(FCPATH . 'uploads', $html);
    }

    public function testDocumentFieldRendersPicker(): void
    {
        $doc = $this->uploadPdf('doc.pdf');
        $html = $this->renderFields(['attachment' => (string) $doc['asset']->id]);

        $this->assertStringContainsString('name="content[attachment]"', $html);
        $this->assertStringContainsString('data-media-type="DOCUMENT"', $html);
        $this->assertStringContainsString('/download/document/', $html);
        $this->assertStringNotContainsString(WRITEPATH . 'uploads/documents', $html);
    }

    public function testEmptyMediaShowsNoSelection(): void
    {
        $html = $this->renderFields([]);
        $this->assertStringContainsString('No media selected.', $html);
        $this->assertStringContainsString('name="content[hero_image]"', $html);
        $this->assertStringContainsString('name="content[attachment]"', $html);
    }

    public function testRichTextUnchangedBesidePicker(): void
    {
        $html = $this->renderFields(['body' => '<p>Hi</p>']);
        $this->assertStringContainsString('data-rich-text="quill"', $html);
        $this->assertStringContainsString('x-data="quillEditor()"', $html);
        $this->assertStringContainsString('name="content[body]"', $html);
        $this->assertStringContainsString('media-picker', $html);
    }

    public function testTwoImageFieldsHaveDistinctBackingNames(): void
    {
        $schema = [
            'a' => ['type' => 'IMAGE', 'label' => 'A', 'required' => false],
            'b' => ['type' => 'IMAGE', 'label' => 'B', 'required' => false],
        ];
        $html = view('admin/pages/_partials/content_fields', [
            'contentSchema'  => $schema,
            'contentPayload' => ['a' => '1', 'b' => '2'],
            'errors'         => [],
        ]);
        $this->assertStringContainsString('name="content[a]"', $html);
        $this->assertStringContainsString('name="content[b]"', $html);
        $this->assertSame(2, substr_count($html, 'class="media-picker"'));
    }

    public function testPickerListFragmentActiveOnlyAndTypeFiltered(): void
    {
        $img = $this->uploadPng('list.png');
        $doc = $this->uploadPdf('list.pdf');
        $imgId = (int) $img['asset']->id;
        $docId = (int) $doc['asset']->id;
        $this->assertSame([], $this->media->trash($docId));

        $imageHtml = view('admin/media/_partials/picker_list', [
            'assets'       => $this->media->listActiveForPicker('IMAGE'),
            'mediaType'    => 'IMAGE',
            'mediaService' => $this->media,
        ]);
        $this->assertStringContainsString('data-pick-media', $imageHtml);
        $this->assertStringContainsString('data-media-id="' . $imgId . '"', $imageHtml);
        $this->assertStringNotContainsString('data-media-id="' . $docId . '"', $imageHtml);

        $docHtml = view('admin/media/_partials/picker_list', [
            'assets'       => $this->media->listActiveForPicker('DOCUMENT'),
            'mediaType'    => 'DOCUMENT',
            'mediaService' => $this->media,
        ]);
        $this->assertStringContainsString('No ACTIVE DOCUMENT assets available', $docHtml);
        $this->assertStringNotContainsString('data-media-id="' . $docId . '"', $docHtml);
        $this->assertStringNotContainsString('data-media-id="' . $imgId . '"', $docHtml);
    }

    public function testInvalidSubmittedMediaIdPreservedInBackingInput(): void
    {
        $html = $this->renderFields(
            ['hero_image' => '99999'],
            ['hero_image' => 'The selected media reference is not valid.'],
        );
        $this->assertStringContainsString('name="content[hero_image]"', $html);
        $this->assertStringContainsString('value="99999"', $html);
        $this->assertStringContainsString('The selected media reference is not valid.', $html);
    }

    /**
     * @param array<string, mixed>  $payload
     * @param array<string, string> $errors
     */
    private function renderFields(array $payload, array $errors = []): string
    {
        $schema = Services::pageService(getShared: false)->contentSchemaForTemplate('custom-page');

        return view('admin/pages/form', [
            'mode'           => 'create',
            'item'           => [
                'title'           => 'T',
                'slug'            => 't',
                'locale'          => 'id',
                'template_key'    => 'custom-page',
                'parent_id'       => null,
                'content_payload' => $payload,
            ],
            'parents'        => [],
            'locales'        => ['id', 'en'],
            'errors'         => $errors,
            'formAction'     => site_url('admin/pages'),
            'contentSchema'  => $schema,
            'contentPayload' => $payload,
            'success'        => null,
            'flashError'     => null,
            'canPublish'     => false,
            'canUnpublish'   => false,
        ]);
    }

    /**
     * @return array{errors: array<string, string>, asset: \App\Entities\MediaAsset|null}
     */
    private function uploadPng(string $name): array
    {
        $dir = WRITEPATH . 'uploads/tmp/fixtures';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/' . $name;
        $im   = imagecreatetruecolor(24, 24);
        imagepng($im, $path);
        imagedestroy($im);

        return $this->media->upload(new MediaUploadDto(
            tmpPath: $path,
            originalFilename: $name,
            clientMime: 'image/png',
            sizeBytes: (int) filesize($path),
        ));
    }

    /**
     * @return array{errors: array<string, string>, asset: \App\Entities\MediaAsset|null}
     */
    private function uploadPdf(string $name): array
    {
        $dir = WRITEPATH . 'uploads/tmp/fixtures';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/' . $name;
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");

        return $this->media->upload(new MediaUploadDto(
            tmpPath: $path,
            originalFilename: $name,
            clientMime: 'application/pdf',
            sizeBytes: (int) filesize($path),
        ));
    }
}
