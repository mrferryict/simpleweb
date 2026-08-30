<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\AuditEvent;
use App\Enums\ThemeState;
use App\Models\AuditLogModel;
use App\Services\Cache\PublicContentCacheInvalidator;
use App\Services\Theme\ThemeService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Config\Theme as ThemeConfig;

/**
 * Theme discovery and lifecycle persistence (Phase 6 / Task 6.1B / ADR-022).
 *
 * @internal
 */
final class ThemeDiscoveryLifecycleTest extends CIUnitTestCase
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

    private ThemeService $themes;

    protected function setUp(): void
    {
        parent::setUp();
        service('settings')->forget('Theme.activeThemeId');
        $this->themes = Services::themeService(getShared: false);
    }

    public function testValidThemesAreDiscovered(): void
    {
        $ids = $this->themes->discoverThemeIds();
        $this->assertContains('default', $ids);
        $this->assertContains('classic', $ids);
        $this->assertContains('2026', $ids);
        $this->assertContains('draft-only', $ids);
    }

    public function testDirectoryWithoutManifestIsExcluded(): void
    {
        $dir = APPPATH . 'Views/themes/no-manifest-dir';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        try {
            $this->assertNotContains('no-manifest-dir', $this->themes->discoverThemeIds());
        } finally {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    public function testInvalidThemeIdentifierIsExcluded(): void
    {
        $dir = APPPATH . 'Views/themes/InvalidTheme';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/ThemeManifest.php', "<?php return ['id' => 'invalidtheme'];");

        try {
            $this->assertNotContains('InvalidTheme', $this->themes->discoverThemeIds());
        } finally {
            @unlink($dir . '/ThemeManifest.php');
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    public function testDirectoryManifestIdMismatchIsExcluded(): void
    {
        $this->assertNotContains('id-mismatch', $this->themes->discoverThemeIds());
    }

    public function testDraftThemeIsNotAdminListed(): void
    {
        $listed = $this->themes->listEnabledThemesForAdmin();
        $ids    = array_column($listed, 'id');
        $this->assertNotContains('draft-only', $ids);
    }

    public function testEnabledThemesAreAdminListed(): void
    {
        $listed = $this->themes->listEnabledThemesForAdmin();
        $ids    = array_column($listed, 'id');
        $this->assertContains('default', $ids);
        $this->assertContains('classic', $ids);
        $this->assertContains('2026', $ids);
    }

    public function testManyThemesCanBeEnabled(): void
    {
        $config = config(ThemeConfig::class);
        $this->assertGreaterThanOrEqual(3, count($config->enabledThemeIds));
        $this->assertCount(3, $this->themes->listEnabledThemesForAdmin());
    }

    public function testExactlyOneActiveThemeResolved(): void
    {
        $this->assertSame('2026', $this->themes->activeThemeId());
        $activeCount = 0;
        foreach ($this->themes->listEnabledThemesForAdmin() as $theme) {
            if ($theme['is_active']) {
                $activeCount++;
            }
        }
        $this->assertSame(1, $activeCount);
    }

    public function testDraftThemeCannotBecomeActive(): void
    {
        $errors = $this->themes->activate('draft-only', $this->userWith(['theme.activate']));
        $this->assertArrayHasKey('theme_id', $errors);
        $this->assertSame('2026', $this->themes->activeThemeId());
    }

    public function testNonEnabledThemeCannotBecomeActive(): void
    {
        $errors = $this->themes->validateActivationCandidate('draft-only');
        $this->assertArrayHasKey('theme_id', $errors);
    }

    public function testInvalidThemeCannotBecomeActive(): void
    {
        $errors = $this->themes->activate('not-real', $this->userWith(['theme.activate']));
        $this->assertArrayHasKey('theme_id', $errors);
    }

    public function testPersistedActiveThemeIdStoredInSettings(): void
    {
        $admin = $this->userWith(['theme.activate']);
        $this->assertSame([], $this->themes->activate('classic', $admin));
        $this->assertSame('classic', Services::settingService(getShared: false)->getPersistedActiveThemeId());
    }

    public function testPersistedSettingsWinsOverBootstrapConfig(): void
    {
        Services::settingService(getShared: false)->setPersistedActiveThemeId('classic');
        $fresh = Services::themeService(getShared: false);
        $this->assertSame('classic', $fresh->activeThemeId());
    }

    public function testBootstrapFallbackWhenNoPersistedValue(): void
    {
        $this->assertNull(Services::settingService(getShared: false)->getPersistedActiveThemeId());
        $this->assertSame('2026', $this->themes->activeThemeId());
    }

    public function testInvalidPersistedActiveDoesNotSilentlyActivateDraft(): void
    {
        Services::settingService(getShared: false)->setPersistedActiveThemeId('draft-only');

        try {
            $fresh = Services::themeService(getShared: false);
            $this->expectException(\RuntimeException::class);
            $fresh->activeThemeId();
        } finally {
            try {
                service('settings')->forget('Theme.activeThemeId');
            } catch (\Throwable) {
            }
        }
    }

    public function testAuthorizedAdminCanActivate(): void
    {
        $this->assertSame([], $this->themes->activate('classic', $this->userWith(['theme.activate'])));
        $this->assertSame('classic', $this->themes->activeThemeId());
    }

    public function testUnauthorizedUserCannotActivate(): void
    {
        $errors = $this->themes->activate('classic', $this->userWithout('theme.activate'));
        $this->assertArrayHasKey('_forbidden', $errors);
        $this->assertSame('2026', $this->themes->activeThemeId());
    }

    public function testActivationDemotesPreviousActiveTheme(): void
    {
        $admin = $this->userWith(['theme.activate']);
        $this->assertSame([], $this->themes->activate('classic', $admin));
        $this->assertSame('classic', $this->themes->activeThemeId());

        $fresh = Services::themeService(getShared: false);
        $listed = $fresh->listEnabledThemesForAdmin();
        $theme2026 = array_values(array_filter($listed, static fn (array $row): bool => $row['id'] === '2026'))[0];
        $classic   = array_values(array_filter($listed, static fn (array $row): bool => $row['id'] === 'classic'))[0];
        $this->assertFalse($theme2026['is_active']);
        $this->assertTrue($classic['is_active']);
        $this->assertSame(ThemeState::Enabled->value, $theme2026['state']);
    }

    public function testPreviousActiveRemainsEnabledAfterActivation(): void
    {
        $admin = $this->userWith(['theme.activate']);
        $this->assertSame([], $this->themes->activate('classic', $admin));
        $this->assertSame(ThemeState::Draft, $this->themes->themeState('draft-only'));
        $this->assertSame(ThemeState::Enabled, $this->themes->themeState('default'));
        $this->assertSame(ThemeState::Enabled, $this->themes->themeState('2026'));
        $this->assertSame(ThemeState::Active, $this->themes->themeState('classic'));
    }

    public function testSuccessfulActivationWritesThemeActivatedAudit(): void
    {
        $admin = $this->userWith(['theme.activate']);
        $this->assertSame([], $this->themes->activate('classic', $admin));

        $row = model(AuditLogModel::class)
            ->where('event', AuditEvent::ThemeActivated->value)
            ->orderBy('id', 'DESC')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('theme', $row->resource_type);
    }

    public function testFailedValidationDoesNotReplaceActiveTheme(): void
    {
        $before = $this->themes->activeThemeId();
        $errors = $this->themes->activate('draft-only', $this->userWith(['theme.activate']));
        $this->assertNotSame([], $errors);
        $this->assertSame($before, $this->themes->activeThemeId());
    }

    public function testFailedValidationDoesNotInvalidateCache(): void
    {
        cache()->save(PublicContentCacheInvalidator::themeActiveKey(), 'cached', 300);
        $errors = $this->themes->activate('draft-only', $this->userWith(['theme.activate']));
        $this->assertNotSame([], $errors);
        $this->assertSame('cached', cache()->get(PublicContentCacheInvalidator::themeActiveKey()));
    }

    public function testSuccessfulActivationInvalidatesThemePresentationCache(): void
    {
        cache()->save(PublicContentCacheInvalidator::themeActiveKey(), 'cached', 300);
        $this->assertSame([], $this->themes->activate('classic', $this->userWith(['theme.activate'])));
        $this->assertNotSame('cached', cache()->get(PublicContentCacheInvalidator::themeActiveKey()));
    }

    public function testManifestLoadFailureHandledSafelyForActivation(): void
    {
        $dir = APPPATH . 'Views/themes/broken-manifest';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        mkdir($dir . '/templates', 0775, true);
        file_put_contents($dir . '/ThemeManifest.php', "<?php throw new \\RuntimeException('broken');");
        file_put_contents($dir . '/templates/custom-page.php', '<?php ?>');
        file_put_contents($dir . '/templates/custom-post.php', '<?php ?>');

        $config = config(ThemeConfig::class);
        $original = $config->enabledThemeIds;
        $config->enabledThemeIds = [...$original, 'broken-manifest'];

        try {
            $service = Services::themeService(getShared: false);
            $this->assertNotContains('broken-manifest', $service->discoverThemeIds());
            $errors = $service->validateActivationCandidate('broken-manifest');
            $this->assertArrayHasKey('theme_id', $errors);
        } finally {
            $config->enabledThemeIds = $original;
            @unlink($dir . '/templates/custom-page.php');
            @unlink($dir . '/templates/custom-post.php');
            @rmdir($dir . '/templates');
            @unlink($dir . '/ThemeManifest.php');
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    public function testThemeStateDraftForDiscoveredNonEnabledTheme(): void
    {
        $this->assertSame(ThemeState::Draft, $this->themes->themeState('draft-only'));
    }

    /**
     * @param list<string> $permissions
     */
    private function userWith(array $permissions): User
    {
        $user = $this->createMock(User::class);
        $user->method('can')->willReturnCallback(
            static fn (string $permission): bool => in_array($permission, $permissions, true),
        );
        $user->method('__get')->willReturnMap([
            ['id', 1],
        ]);

        return $user;
    }

    private function userWithout(string $denied): User
    {
        $user = $this->createMock(User::class);
        $user->method('can')->willReturnCallback(
            static fn (string $permission): bool => $permission !== $denied,
        );
        $user->method('__get')->willReturnMap([
            ['id', 2],
        ]);

        return $user;
    }

    protected function tearDown(): void
    {
        try {
            service('settings')->forget('Theme.activeThemeId');
        } catch (\Throwable) {
        }

        parent::tearDown();
    }
}
