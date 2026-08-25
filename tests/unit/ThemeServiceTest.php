<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Content\ContentSchemaValidator;
use App\Services\Theme\ThemeService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use Config\Theme as ThemeConfig;
use RuntimeException;

/**
 * Baseline Theme Manifest foundation (Phase 3 / Task 3.2 / ADR-002).
 *
 * @internal
 */
final class ThemeServiceTest extends CIUnitTestCase
{
    private ThemeService $themeService;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            service('settings')->forget('Theme.activeThemeId');
        } catch (\Throwable) {
            // ThemeServiceTest does not migrate the Settings package table.
        }
        $this->themeService = Services::themeService(getShared: false);
    }

    public function testManifestLoadsSuccessfully(): void
    {
        $manifest = $this->themeService->loadActiveManifest();
        $this->assertIsArray($manifest);
        $this->assertSame([], $this->themeService->validateManifestStructure($manifest));
    }

    public function testManifestHasRequiredTopLevelKeys(): void
    {
        $manifest = $this->themeService->loadActiveManifest();
        foreach (['id', 'name', 'version', 'author', 'templates', 'media_profiles'] as $key) {
            $this->assertArrayHasKey($key, $manifest);
        }
    }

    public function testThemeIdentityIsCorrect(): void
    {
        $this->assertSame('default', $this->themeService->activeThemeId());
        $manifest = $this->themeService->loadActiveManifest();
        $this->assertSame('default', $manifest['id']);
        $this->assertSame('Default', $manifest['name']);
        $this->assertSame('SMITE CMS', $manifest['author']);
    }

    public function testThemeVersionIsPresent(): void
    {
        $manifest = $this->themeService->loadActiveManifest();
        $this->assertNotSame('', trim($manifest['version']));
        $this->assertSame('1.0.0', $manifest['version']);
    }

    public function testCustomPageTemplateResolves(): void
    {
        $this->assertTrue($this->themeService->hasTemplate('custom-page'));
        $manifest = $this->themeService->loadActiveManifest();
        $this->assertArrayHasKey('custom-page', $manifest['templates']);
        $this->assertArrayHasKey('fields', $manifest['templates']['custom-page']);
    }

    public function testCustomPostTemplateExistsOnActiveTheme(): void
    {
        $this->assertTrue($this->themeService->hasTemplate('custom-post'));
        $manifest = $this->themeService->loadActiveManifest();
        $this->assertArrayHasKey('custom-post', $manifest['templates']);
        $this->assertArrayHasKey('fields', $manifest['templates']['custom-post']);
        $this->assertSame('Custom Post', $manifest['templates']['custom-post']['label']);
    }

    public function testCustomPageSchemaResolves(): void
    {
        $schema = $this->themeService->contentSchemaForTemplate('custom-page');
        $this->assertNotSame([], $schema);
        $this->assertArrayHasKey('hero_title', $schema);
        $this->assertSame('TEXT', $schema['hero_title']['type']);
        $this->assertArrayHasKey('hero_slides', $schema);
        $this->assertSame('REPEATABLE', $schema['hero_slides']['type']);
    }

    public function testCustomPostSchemaResolvesWithBodyRichText(): void
    {
        $schema = $this->themeService->contentSchemaForTemplate('custom-post');
        $this->assertArrayHasKey('body', $schema);
        $this->assertSame('RICH_TEXT', $schema['body']['type']);
        $this->assertFalse((bool) ($schema['body']['required'] ?? false));
        $this->assertCount(1, $schema);
    }

    public function testSchemaIsAcceptedByContentSchemaValidator(): void
    {
        $schema    = $this->themeService->contentSchemaForTemplate('custom-page');
        $validator = new ContentSchemaValidator();

        $empty = $validator->validate([], $schema);
        $this->assertTrue($empty->ok, json_encode($empty->errors) ?: '');

        $valid = $validator->validate([
            'hero_title' => ' Welcome ',
            'cta_url'    => 'https://example.com/about',
            'hero_slides' => [
                ['title' => 'Slide', 'url' => 'https://example.com/a'],
            ],
        ], $schema);
        $this->assertTrue($valid->ok, json_encode($valid->errors) ?: '');
        $this->assertSame('Welcome', $valid->normalized['hero_title']);
    }

    public function testCustomPostEmptyBodyIsAcceptedByValidator(): void
    {
        $schema    = $this->themeService->contentSchemaForTemplate('custom-post');
        $validator = new ContentSchemaValidator();
        $empty     = $validator->validate([], $schema);
        $this->assertTrue($empty->ok, json_encode($empty->errors) ?: '');
    }

    public function testInvalidTemplateKeyIsRejectedSafely(): void
    {
        $this->assertFalse($this->themeService->hasTemplate('not-a-real-template'));
        try {
            $unused = $this->themeService->contentSchemaForTemplate('not-a-real-template');
            unset($unused);
            $this->fail('Expected RuntimeException for unknown template key.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Template is not available', $e->getMessage());
        }
    }

    public function testManifestPathIsOutsidePublicWebRoot(): void
    {
        $path = $this->themeService->activeManifestPath();
        $this->assertStringContainsString('Views/themes/default/ThemeManifest.php', $path);
        $this->assertStringNotContainsString('/public/', $path);
        $this->assertFileExists($path);
    }

    public function testIncompleteManifestStructureIsRejected(): void
    {
        $errors = $this->themeService->validateManifestStructure([
            'id' => 'broken',
        ]);
        $this->assertNotSame([], $errors);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('templates', $errors);
    }

    public function testManifestWithoutCustomPageIsRejected(): void
    {
        $errors = $this->themeService->validateManifestStructure([
            'id'              => 'x',
            'name'            => 'X',
            'version'         => '1.0.0',
            'author'          => 'Dev',
            'media_profiles'  => [],
            'templates'       => [
                'custom-post' => ['fields' => ['body' => ['type' => 'RICH_TEXT']]],
            ],
        ]);
        $this->assertArrayHasKey('custom-page', $errors);
    }

    public function testManifestWithoutCustomPostIsRejected(): void
    {
        $errors = $this->themeService->validateManifestStructure([
            'id'              => 'x',
            'name'            => 'X',
            'version'         => '1.0.0',
            'author'          => 'Dev',
            'media_profiles'  => [],
            'templates'       => [
                'custom-page' => ['fields' => ['t' => ['type' => 'TEXT']]],
            ],
        ]);
        $this->assertArrayHasKey('custom-post', $errors);
    }

    public function testActiveThemeConfigIsUsed(): void
    {
        $config = new ThemeConfig();
        $this->assertSame('default', $config->activeThemeId);
        $this->assertContains('default', $config->enabledThemeIds);
    }

    public function testPublicCustomPostViewPathResolves(): void
    {
        $path = $this->themeService->publicViewPathForTemplate('custom-post');
        $this->assertStringContainsString('Views/themes/default/templates/custom-post.php', $path);
        $this->assertFileExists($path);
        $this->assertSame(
            'themes/default/templates/custom-post',
            $this->themeService->publicViewNameForTemplate('custom-post'),
        );
    }

    public function testCmsDefaultImageProfileIsInThemeCatalog(): void
    {
        $profiles = $this->themeService->mediaProfiles();
        $this->assertArrayHasKey(ThemeService::BASELINE_IMAGE_PROFILE_ID, $profiles);

        $resolved = $this->themeService->resolveImageProfile(null);
        $this->assertSame('cms_default', $resolved['id']);
        $this->assertSame(2560, $resolved['maximum_width']);
        $this->assertSame(2560, $resolved['maximum_height']);
        $this->assertSame(5_242_880, $resolved['maximum_file_size']);
        $this->assertNull($resolved['minimum_width']);
        $this->assertNull($resolved['minimum_height']);
        $this->assertContains('png', $resolved['allowed_formats']);
        $this->assertContains('webp', $resolved['allowed_formats']);
        $this->assertContains('jpeg', $resolved['allowed_formats']);
        $this->assertContains('gif', $resolved['allowed_formats']);
        $this->assertArrayNotHasKey('quality', $resolved);
        $this->assertArrayNotHasKey('aspect_ratio', $resolved);
        $this->assertArrayNotHasKey('output_format', $resolved);
    }

    public function testHeroImageFieldReferencesCmsDefaultProfile(): void
    {
        $schema = $this->themeService->contentSchemaForTemplate('custom-page');
        $this->assertSame('IMAGE', $schema['hero_image']['type'] ?? null);
        $this->assertSame('cms_default', $schema['hero_image']['media_profile'] ?? null);
    }

    public function testUnknownProfileFallsBackToBuiltInCmsDefault(): void
    {
        $resolved = $this->themeService->resolveImageProfile('does-not-exist');
        $this->assertSame('cms_default', $resolved['id']);
        $this->assertSame(2560, $resolved['maximum_width']);
    }
}
