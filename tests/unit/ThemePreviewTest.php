<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\ThemePreviewController;
use App\Dtos\PageWriteDto;
use App\Enums\PageStatus;
use App\Models\AuditLogModel;
use App\Models\RevisionModel;
use App\Services\PageService;
use App\Services\SettingService;
use App\Services\Theme\ThemeService;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * Theme Preview contract (Phase 6 / Task 6.2B / ADR-023).
 *
 * @internal
 */
final class ThemePreviewTest extends CIUnitTestCase
{
    use ControllerTestTrait;
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

    private PageService $pages;
    private ThemeService $themes;
    private SettingService $settings;

    protected function setUp(): void
    {
        parent::setUp();
        service('settings')->forget('Theme.activeThemeId');
        $this->pages    = Services::pageService(getShared: false);
        $this->themes   = Services::themeService(getShared: false);
        $this->settings = Services::settingService(getShared: false);
    }

    protected function tearDown(): void
    {
        Services::resetSingle('auth');
        parent::tearDown();
    }

    public function testAdminWithThemePreviewCanResolvePreview(): void
    {
        $admin = $this->adminPreviewActor();
        $id    = $this->createPage($admin, 'Preview Me', 'preview-me');

        $this->assertSame([], $this->themes->validatePreviewCandidate('classic', $admin));
        $dto = $this->pages->findForThemePreview($id, 'id', 'classic');
        $this->assertNotNull($dto);
        $this->assertSame('Preview Me', $dto->title);
    }

    public function testEditorDeniedThemePreview(): void
    {
        $editor = $this->actorWith(['page.edit']);
        $errors = $this->themes->validatePreviewCandidate('classic', $editor);
        $this->assertArrayHasKey('_forbidden', $errors);
    }

    public function testContributorDeniedThemePreview(): void
    {
        $contributor = $this->actorWith(['post.create']);
        $errors      = $this->themes->validatePreviewCandidate('classic', $contributor);
        $this->assertArrayHasKey('_forbidden', $errors);
    }

    public function testEnabledThemeCanPreview(): void
    {
        $this->assertSame([], $this->themes->validatePreviewCandidate('classic', $this->adminPreviewActor()));
    }

    public function testActiveThemeCanPreview(): void
    {
        $this->assertSame([], $this->themes->validatePreviewCandidate('default', $this->adminPreviewActor()));
    }

    public function testDraftThemeDenied(): void
    {
        $errors = $this->themes->validatePreviewCandidate('draft-only', $this->adminPreviewActor());
        $this->assertArrayHasKey('theme_id', $errors);
    }

    public function testInvalidThemeIdDenied(): void
    {
        $errors = $this->themes->validatePreviewCandidate('NOT_VALID', $this->adminPreviewActor());
        $this->assertArrayHasKey('theme_id', $errors);
    }

    public function testManifestMismatchThemeDenied(): void
    {
        $errors = $this->themes->validatePreviewCandidate('id-mismatch', $this->adminPreviewActor());
        $this->assertArrayHasKey('theme_id', $errors);
    }

    public function testMissingThemeDenied(): void
    {
        $errors = $this->themes->validatePreviewCandidate('does-not-exist', $this->adminPreviewActor());
        $this->assertArrayHasKey('theme_id', $errors);
    }

    public function testDraftPageCanPreview(): void
    {
        $admin = $this->adminPreviewActor();
        $id    = $this->createPage($admin, 'Draft Page', 'draft-page');
        $this->assertSame(PageStatus::Draft->value, $this->pages->findById($id)?->status);
        $this->assertNotNull($this->pages->findForThemePreview($id, 'id', 'classic'));
    }

    public function testPublishedPageCanPreview(): void
    {
        $admin = $this->adminPreviewActor();
        $id    = $this->createPage($admin, 'Published Page', 'published-page');
        $this->assertSame([], $this->pages->publish($id, $admin));
        $this->assertNotNull($this->pages->findForThemePreview($id, 'id', 'classic'));
    }

    public function testUnpublishedPageCanPreview(): void
    {
        $admin = $this->adminPreviewActor();
        $id    = $this->createPage($admin, 'Unpub Page', 'unpub-page');
        $this->assertSame([], $this->pages->publish($id, $admin));
        $this->assertSame([], $this->pages->unpublish($id, $admin));
        $this->assertNotNull($this->pages->findForThemePreview($id, 'id', 'classic'));
    }

    public function testArchivedPageCanPreview(): void
    {
        $admin = $this->adminPreviewActor();
        $id    = $this->createPage($admin, 'Archived Page', 'archived-page');
        $this->assertSame([], $this->pages->publish($id, $admin));
        $this->assertSame([], $this->pages->archive($id, $admin));
        $this->assertNotNull($this->pages->findForThemePreview($id, 'id', 'classic'));
    }

    public function testTrashPageDenied(): void
    {
        $admin = $this->adminPreviewActor();
        $id    = $this->createPage($admin, 'Trash Page', 'trash-page');
        $this->assertSame([], $this->pages->trash($id, $admin));
        $this->assertNull($this->pages->findForThemePreview($id, 'id', 'classic'));
    }

    public function testMissingPageDenied(): void
    {
        $this->assertNull($this->pages->findForThemePreview(999_999, 'id', 'classic'));
    }

    public function testPreviewDoesNotChangeActiveThemeSettings(): void
    {
        $admin          = $this->adminPreviewActor();
        $id             = $this->createPage($admin, 'Iso Page', 'iso-page');
        $beforeSettings = $this->settings->getPersistedActiveThemeId();
        $beforeActive   = $this->themes->activeThemeId();

        $this->injectAuth($admin);
        $this->executePreview('classic', $id);

        $this->assertSame($beforeSettings, $this->settings->getPersistedActiveThemeId());
        $this->assertSame($beforeActive, $this->themes->activeThemeId());
    }

    public function testPreviewDoesNotMutatePageStatusOrLockVersion(): void
    {
        $admin  = $this->adminPreviewActor();
        $id     = $this->createPage($admin, 'Lock Page', 'lock-page');
        $before = $this->pages->findById($id);
        $this->assertNotNull($before);

        $revisionCount = model(RevisionModel::class)->countAllResults();
        $auditCount    = model(AuditLogModel::class)->countAllResults();

        $this->injectAuth($admin);
        $this->executePreview('classic', $id);

        $after = $this->pages->findById($id);
        $this->assertNotNull($after);
        $this->assertSame($before->status, $after->status);
        $this->assertSame($before->lock_version, $after->lock_version);
        $this->assertSame($revisionCount, model(RevisionModel::class)->countAllResults());
        $this->assertSame($auditCount, model(AuditLogModel::class)->countAllResults());
    }

    public function testPreviewDoesNotWritePublicCacheKeys(): void
    {
        $admin = $this->adminPreviewActor();
        $id    = $this->createPage($admin, 'Cache Page', 'cache-page');
        $cache = Services::cache(getShared: false);
        $cache->save('page.public.' . $id, ['seed' => true], 60);

        $this->injectAuth($admin);
        $this->executePreview('classic', $id);

        $cached = $cache->get('page.public.' . $id);
        $this->assertIsArray($cached);
        $this->assertSame(['seed' => true], $cached);
        $this->assertNull($cache->get('page.public.preview.' . $id));
    }

    public function testPreviewResponseIncludesSecurityHeaders(): void
    {
        $admin = $this->adminPreviewActor();
        $id    = $this->createPage($admin, 'Header Page', 'header-page');

        $this->injectAuth($admin);
        $result = $this->executePreview('classic', $id);

        $result->assertOK();
        $response = $result->response();
        $cacheControl = $response->getHeaderLine('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertSame('no-cache', $response->getHeaderLine('Pragma'));
        $this->assertSame('noindex, nofollow, noarchive', $response->getHeaderLine('X-Robots-Tag'));
    }

    public function testPreviewUsesCandidateThemeViewPath(): void
    {
        $admin = $this->adminPreviewActor();
        $id    = $this->createPage($admin, 'Classic View', 'classic-view');

        $this->injectAuth($admin);
        $result = $this->executePreview('classic', $id);

        $result->assertOK();
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Classic View', $body);
        $this->assertStringNotContainsString('hero-title', $body);
    }

    public function testDefaultLocaleUsedWhenOmitted(): void
    {
        $admin = $this->adminPreviewActor();
        $id    = $this->createPage($admin, 'Locale Default', 'locale-default');

        $this->injectAuth($admin);
        $result = $this->executePreview('classic', $id);

        $result->assertOK();
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Locale Default', $body);
    }

    public function testExplicitSupportedLocaleWorks(): void
    {
        $admin = $this->adminPreviewActor();
        $id    = $this->createPage($admin, 'Locale ID', 'locale-id');

        $this->injectAuth($admin);
        $result = $this->executePreview('classic', $id, ['locale' => 'id']);

        $result->assertOK();
    }

    public function testUnsupportedLocaleRejected(): void
    {
        $admin = $this->adminPreviewActor();
        $id    = $this->createPage($admin, 'Locale Bad', 'locale-bad');

        $this->injectAuth($admin);
        $result = $this->executePreview('classic', $id, ['locale' => 'fr']);

        $this->assertSame(404, $result->response()->getStatusCode());
    }

    public function testMissingTranslationForLocaleRejected(): void
    {
        $admin = $this->adminPreviewActor();
        $id    = $this->createPage($admin, 'Only ID', 'only-id');

        $this->assertNull($this->pages->findForThemePreview($id, 'en', 'classic'));
    }

    public function testPublicViewNameForThemeTemplateIsRequestScoped(): void
    {
        $viewName = $this->themes->publicViewNameForThemeTemplate('classic', 'custom-page');
        $this->assertSame('themes/classic/templates/custom-page', $viewName);
        $this->assertSame('2026', $this->themes->activeThemeId());
    }

    public function testThemesAdminListsPreviewLinksWhenPagesExist(): void
    {
        $admin = $this->adminPreviewActor();
        $this->createPage($admin, 'Admin Link', 'admin-link');
        $this->injectAuth($admin);

        $result = $this->withUri('http://example.com/admin/themes')
            ->controller(\App\Controllers\Admin\ThemeController::class)
            ->execute('index');

        $result->assertOK();
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('admin/preview/theme/classic/page/', $body);
        $this->assertStringContainsString('Preview:', $body);
    }

    /**
     * @param list<string> $permissions
     */
    private function actorWith(array $permissions): User
    {
        $user = $this->createMock(User::class);
        $user->method('can')->willReturnCallback(
            static fn (string $p): bool => in_array($p, $permissions, true),
        );
        $user->id = 1;

        return $user;
    }

    private function adminPreviewActor(): User
    {
        return $this->actorWith([
            'page.create',
            'page.edit',
            'page.publish',
            'page.unpublish',
            'page.archive',
            'page.trash',
            'theme.preview',
            'theme.activate',
        ]);
    }

    private function injectAuth(User $user): void
    {
        $auth = $this->getMockBuilder(Auth::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['user', 'id', 'setAuthenticator'])
            ->getMock();
        $auth->method('setAuthenticator')->willReturnSelf();
        $auth->method('user')->willReturn($user);
        $auth->method('id')->willReturn(1);

        Services::injectMock('auth', $auth);
    }

    private function createPage(User $actor, string $title, string $slug): int
    {
        $this->assertSame([], $this->pages->create(new PageWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
        ), $actor));

        return (int) $this->pages->listActive()[0]['page']->id;
    }

    /**
     * @param array<string, string> $query
     */
    private function executePreview(string $themeId, int $pageId, array $query = [])
    {
        /** @var IncomingRequest $request */
        $request = service('incomingrequest', null, false);
        $request->setGlobal('get', $query);
        $request->setMethod('get');

        $uri = 'http://example.com/admin/preview/theme/' . $themeId . '/page/' . $pageId;
        if ($query !== []) {
            $uri .= '?' . http_build_query($query);
        }

        return $this->withUri($uri)
            ->withRequest($request)
            ->controller(ThemePreviewController::class)
            ->execute('show', $themeId, $pageId);
    }
}
