<?php

namespace Config;

use App\Models\CategoryModel;
use App\Models\MediaAssetModel;
use App\Models\MenuModel;
use App\Models\PageModel;
use App\Models\PageTranslationModel;
use App\Models\PostModel;
use App\Models\PostTranslationModel;
use App\Models\RevisionModel;
use App\Models\ScheduledActionModel;
use App\Models\TagModel;
use App\Services\Audit\AuditService;
use App\Services\Cache\PublicContentCacheInvalidator;
use App\Models\UrlRedirectModel;
use App\Services\Localization\LocaleContext;
use App\Services\Localization\PublicUrlBuilder;
use App\Services\Localization\PublicUrlNamespaceValidator;
use App\Services\Localization\PublicUrlResolverService;
use App\Services\Localization\RobotsService;
use App\Services\Localization\SeoService;
use App\Services\Localization\SitemapService;
use App\Services\Localization\UrlRedirectService;
use App\Services\CategoryService;
use App\Services\Content\ContentPermanentDeleteDependencyChecker;
use App\Services\Content\ContentSchemaValidator;
use App\Services\Media\MediaDependencyChecker;
use App\Services\Media\MediaService;
use App\Services\MenuService;
use App\Services\PageService;
use App\Services\PostService;
use App\Services\Revision\RevisionService;
use App\Services\ScheduledContentService;
use App\Services\Demo\DemoContentService;
use App\Services\Install\InstallService;
use App\Services\Security\AuthThrottleService;
use App\Services\Security\PasswordPolicyService;
use App\Services\Security\PasswordResetEmailService;
use App\Services\Security\PiiCipherService;
use App\Services\Security\RichTextSanitizer;
use App\Services\Security\SmtpPasswordResetEmailTransport;
use App\Services\Security\UserEmailService;
use App\Services\SettingService;
use App\Services\TagService;
use App\Services\Theme\ThemeService;
use App\Services\UserAdminService;
use CodeIgniter\Config\BaseService;
use CodeIgniter\Settings\Config\Services as SettingsServices;
use CodeIgniter\Settings\Settings;
use Config\AuthGroups;
use Config\AuthThrottle;
use Config\Email as EmailConfig;
use Config\EmailPii;
use Config\Theme as ThemeConfig;

/**
 * Application services.
 */
class Services extends BaseService
{
    /**
     * Site Settings application service (Phase 2 / Task 2.1).
     */
    public static function settingService(bool $getShared = true): SettingService
    {
        if ($getShared) {
            return static::getSharedInstance('settingService');
        }

        /** @var Settings $settings */
        $settings = SettingsServices::settings(getShared: true);

        return new SettingService(
            $settings,
            static::validation(getShared: false),
            static::publicContentCacheInvalidator(getShared: $getShared),
        );
    }

    /**
     * Menu application service (Phase 2 / Task 2.2).
     */
    public static function menuService(bool $getShared = true): MenuService
    {
        if ($getShared) {
            return static::getSharedInstance('menuService');
        }

        /** @var MenuModel $model */
        $model = model(MenuModel::class);

        return new MenuService($model, static::validation(getShared: false));
    }

    /**
     * Page foundation application service (Phase 2 / Task 2.5).
     */
    public static function pageService(bool $getShared = true): PageService
    {
        if ($getShared) {
            return static::getSharedInstance('pageService');
        }

        /** @var PageModel $pageModel */
        $pageModel = model(PageModel::class);
        /** @var PageTranslationModel $translationModel */
        $translationModel = model(PageTranslationModel::class);

        $db = db_connect();

        return new PageService(
            $pageModel,
            $translationModel,
            static::validation(getShared: false),
            $db,
            static::contentSchemaValidator(getShared: true),
            static::themeService(getShared: true),
            static::richTextSanitizer(getShared: true),
            static::mediaService(getShared: true),
            static::revisionService(getShared: true),
            static::auditService(getShared: true),
            new ContentPermanentDeleteDependencyChecker($db),
            static::publicContentCacheInvalidator(getShared: $getShared),
            static::settingService(getShared: true),
            static::publicUrlNamespaceValidator(getShared: true),
            static::urlRedirectService(getShared: true),
            static::seoService(getShared: true),
            static::publicUrlBuilder(getShared: true),
        );
    }

    /**
     * Content Schema validator (Phase 3 / Task 3.1 / ADR-004).
     * Media resolver wired when MediaService is available (ADR-018).
     */
    public static function contentSchemaValidator(bool $getShared = true): ContentSchemaValidator
    {
        if ($getShared) {
            return static::getSharedInstance('contentSchemaValidator');
        }

        $media = static::mediaService(getShared: true);

        return new ContentSchemaValidator(
            static fn (int $mediaId, string $kind): bool => $media->isValidReference($mediaId, $kind),
        );
    }

    /**
     * Media Library foundation (Phase 4 / Task 4.5 / ADR-018).
     */
    public static function mediaService(bool $getShared = true): MediaService
    {
        if ($getShared) {
            return static::getSharedInstance('mediaService');
        }

        /** @var MediaAssetModel $model */
        $model = model(MediaAssetModel::class);

        return new MediaService(
            $model,
            new MediaDependencyChecker(db_connect()),
            static::themeService(getShared: true),
        );
    }

    /**
     * RICH_TEXT HTML sanitizer (Phase 3 / Task 3.4 / ADR-014).
     */
    public static function richTextSanitizer(bool $getShared = true): RichTextSanitizer
    {
        if ($getShared) {
            return static::getSharedInstance('richTextSanitizer');
        }

        return new RichTextSanitizer();
    }

    /**
     * ADR-008 email PII cipher.
     */
    public static function piiCipherService(bool $getShared = true): PiiCipherService
    {
        if ($getShared) {
            return static::getSharedInstance('piiCipherService');
        }

        /** @var EmailPii $config */
        $config = config(EmailPii::class);

        return new PiiCipherService($config);
    }

    /**
     * ADR-008 user email persistence/lookup.
     */
    public static function userEmailService(bool $getShared = true): UserEmailService
    {
        if ($getShared) {
            return static::getSharedInstance('userEmailService');
        }

        return new UserEmailService(
            static::piiCipherService(getShared: true),
            db_connect(),
        );
    }

    /**
     * V2-003 Control Panel staff user management (ADR-027 P0-1).
     */
    public static function userAdminService(bool $getShared = true): UserAdminService
    {
        if ($getShared) {
            return static::getSharedInstance('userAdminService');
        }

        /** @var AuthGroups $authGroups */
        $authGroups = config(AuthGroups::class);

        return new UserAdminService(
            db_connect(),
            static::userEmailService(getShared: true),
            static::auditService(getShared: true),
            static::passwordPolicyService(getShared: true),
            $authGroups,
        );
    }

    /**
     * V2-005 Shield password policy entry point (ADR-027 P0-3).
     */
    public static function passwordPolicyService(bool $getShared = true): PasswordPolicyService
    {
        if ($getShared) {
            return static::getSharedInstance('passwordPolicyService');
        }

        return new PasswordPolicyService(static::passwords(getShared: true));
    }

    /**
     * V2-004 password-reset email delivery (ADR-027 P0-2).
     */
    public static function passwordResetEmailService(bool $getShared = true): PasswordResetEmailService
    {
        if ($getShared) {
            return static::getSharedInstance('passwordResetEmailService');
        }

        /** @var EmailConfig $emailConfig */
        $emailConfig = config(EmailConfig::class);

        return new PasswordResetEmailService(
            static::settingService(getShared: true),
            new SmtpPasswordResetEmailTransport($emailConfig),
        );
    }

    /**
     * ADR-026 auth surface throttling.
     */
    public static function authThrottleService(bool $getShared = true): AuthThrottleService
    {
        if ($getShared) {
            return static::getSharedInstance('authThrottleService');
        }

        /** @var AuthThrottle $config */
        $config = config(AuthThrottle::class);

        return new AuthThrottleService(
            static::throttler(getShared: true),
            $config,
        );
    }

    /**
     * Production installer bootstrap (DOC-09 §5 / DOC-10 §63 / DOC-11 §11–12).
     */
    public static function installService(bool $getShared = true): InstallService
    {
        if ($getShared) {
            return static::getSharedInstance('installService');
        }

        return new InstallService(
            db_connect(),
            SettingsServices::settings(getShared: true),
            static::userEmailService(getShared: true),
        );
    }

    /**
     * Optional starter content for `cms:demo` (post-V1 / TH-004).
     */
    public static function demoContentService(bool $getShared = true): DemoContentService
    {
        if ($getShared) {
            return static::getSharedInstance('demoContentService');
        }

        return new DemoContentService(
            static::pageService(getShared: true),
            static::postService(getShared: true),
            model(\App\Models\PageTranslationModel::class),
            model(\App\Models\PostTranslationModel::class),
            static::settingService(getShared: true),
            static::installService(getShared: false),
            db_connect(),
        );
    }

    /**
     * Theme Manifest loader / schema resolver (Phase 3 / Task 3.2 / ADR-002).
     */
    public static function themeService(bool $getShared = true): ThemeService
    {
        if ($getShared) {
            return static::getSharedInstance('themeService');
        }

        /** @var ThemeConfig $themeConfig */
        $themeConfig = config(ThemeConfig::class);

        return new ThemeService(
            $themeConfig,
            static::settingService(getShared: true),
            static::auditService(getShared: true),
            static::publicContentCacheInvalidator(getShared: $getShared),
            db_connect(),
        );
    }

    /**
     * Category foundation (Phase 3 / Task 3.7).
     */
    public static function categoryService(bool $getShared = true): CategoryService
    {
        if ($getShared) {
            return static::getSharedInstance('categoryService');
        }

        /** @var CategoryModel $model */
        $model = model(CategoryModel::class);

        return new CategoryService($model, static::validation(getShared: false), db_connect());
    }

    /**
     * Tag foundation (Phase 3 / Task 3.7).
     */
    public static function tagService(bool $getShared = true): TagService
    {
        if ($getShared) {
            return static::getSharedInstance('tagService');
        }

        /** @var TagModel $model */
        $model = model(TagModel::class);

        return new TagService($model, static::validation(getShared: false), db_connect());
    }

    /**
     * Post foundation (Phase 3 / Task 3.7).
     */
    public static function postService(bool $getShared = true): PostService
    {
        if ($getShared) {
            return static::getSharedInstance('postService');
        }

        /** @var PostModel $postModel */
        $postModel = model(PostModel::class);
        /** @var PostTranslationModel $translationModel */
        $translationModel = model(PostTranslationModel::class);

        $db = db_connect();

        return new PostService(
            $postModel,
            $translationModel,
            static::categoryService(getShared: false),
            static::tagService(getShared: false),
            static::validation(getShared: false),
            $db,
            static::contentSchemaValidator(getShared: true),
            static::richTextSanitizer(getShared: true),
            static::themeService(getShared: true),
            static::revisionService(getShared: true),
            static::auditService(getShared: true),
            new ContentPermanentDeleteDependencyChecker($db),
            static::publicContentCacheInvalidator(getShared: $getShared),
            static::settingService(getShared: true),
            static::publicUrlNamespaceValidator(getShared: true),
            static::urlRedirectService(getShared: true),
            static::publicUrlBuilder(getShared: true),
            static::seoService(getShared: true),
        );
    }

    /**
     * Audit append-only service (Phase 4 / Task 4.9B / ADR-019).
     */
    public static function auditService(bool $getShared = true): AuditService
    {
        if ($getShared) {
            return static::getSharedInstance('auditService');
        }

        /** @var \App\Models\AuditLogModel $model */
        $model = model(\App\Models\AuditLogModel::class);

        return new AuditService($model);
    }

    /**
     * Revision persistence service (Phase 4 / Task 4.9B / ADR-019).
     */
    public static function revisionService(bool $getShared = true): RevisionService
    {
        if ($getShared) {
            return static::getSharedInstance('revisionService');
        }

        return new RevisionService(
            model(RevisionModel::class),
            model(PostModel::class),
            model(PostTranslationModel::class),
            model(PageModel::class),
            model(PageTranslationModel::class),
            static::auditService(getShared: true),
            db_connect(),
        );
    }

    /**
     * Scheduled publish/unpublish (Phase 4 / Task 4.12C / ADR-021).
     */
    public static function scheduledContentService(bool $getShared = true): ScheduledContentService
    {
        if ($getShared) {
            return static::getSharedInstance('scheduledContentService');
        }

        return new ScheduledContentService(
            model(ScheduledActionModel::class),
            static::pageService(getShared: true),
            static::postService(getShared: true),
            static::settingService(getShared: true),
            static::publicContentCacheInvalidator(getShared: $getShared),
            db_connect(),
        );
    }

    /**
     * Public Page/Post cache invalidation (Phase 4 / Task 4.13 / ADR-009).
     */
    public static function publicContentCacheInvalidator(bool $getShared = true): PublicContentCacheInvalidator
    {
        if ($getShared) {
            return static::getSharedInstance('publicContentCacheInvalidator');
        }

        return new PublicContentCacheInvalidator(static::cache(getShared: true));
    }

    public static function localeContext(bool $getShared = true): LocaleContext
    {
        if ($getShared) {
            return static::getSharedInstance('localeContext');
        }

        return new LocaleContext();
    }

    public static function publicUrlBuilder(bool $getShared = true): PublicUrlBuilder
    {
        if ($getShared) {
            return static::getSharedInstance('publicUrlBuilder');
        }

        return new PublicUrlBuilder(static::settingService(getShared: true));
    }

    public static function urlRedirectService(bool $getShared = true): UrlRedirectService
    {
        if ($getShared) {
            return static::getSharedInstance('urlRedirectService');
        }

        return new UrlRedirectService(
            model(UrlRedirectModel::class),
            static::publicUrlBuilder(getShared: true),
            db_connect(),
        );
    }

    public static function publicUrlNamespaceValidator(bool $getShared = true): PublicUrlNamespaceValidator
    {
        if ($getShared) {
            return static::getSharedInstance('publicUrlNamespaceValidator');
        }

        return new PublicUrlNamespaceValidator(
            model(PageTranslationModel::class),
            model(PostTranslationModel::class),
            model(UrlRedirectModel::class),
            static::publicUrlBuilder(getShared: true),
            static::settingService(getShared: true),
        );
    }

    public static function publicUrlResolverService(bool $getShared = true): PublicUrlResolverService
    {
        if ($getShared) {
            return static::getSharedInstance('publicUrlResolverService');
        }

        return new PublicUrlResolverService(
            static::urlRedirectService(getShared: true),
            model(UrlRedirectModel::class),
            static::pageService(getShared: true),
            static::postService(getShared: true),
            static::publicUrlBuilder(getShared: true),
        );
    }

    public static function seoService(bool $getShared = true): SeoService
    {
        if ($getShared) {
            return static::getSharedInstance('seoService');
        }

        return new SeoService(
            static::settingService(getShared: true),
            model(PageTranslationModel::class),
            model(PostTranslationModel::class),
            model(PageModel::class),
            model(PostModel::class),
            static::publicUrlBuilder(getShared: true),
            static::mediaService(getShared: true),
        );
    }

    public static function sitemapService(bool $getShared = true): SitemapService
    {
        if ($getShared) {
            return static::getSharedInstance('sitemapService');
        }

        return new SitemapService(
            model(PageModel::class),
            model(PageTranslationModel::class),
            model(PostModel::class),
            model(PostTranslationModel::class),
            model(UrlRedirectModel::class),
            static::publicUrlBuilder(getShared: true),
            static::settingService(getShared: true),
        );
    }

    public static function robotsService(bool $getShared = true): RobotsService
    {
        if ($getShared) {
            return static::getSharedInstance('robotsService');
        }

        return new RobotsService();
    }
}
