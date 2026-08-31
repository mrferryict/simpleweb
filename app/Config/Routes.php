<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', 'Home::index');

/*
 |--------------------------------------------------------------------
 | Control Panel authentication entry routes (ADR-001 / DOC-08 §28)
 |--------------------------------------------------------------------
 | Entry point is /cp (not /login). Authenticated CP lives under /admin/*.
 |
 | Named route `login` is required by Shield SessionAuth filter
 | (redirect()->route('login')) and resolves to /cp.
 |
 | Logout is registered outside the session-auth filter group and
 | matches the CSRF exception URI `logout` (.cursorrules §4.3).
 */

$routes->get('cp', 'Auth\AuthController::login', ['as' => 'login']);
$routes->post('cp', 'Auth\AuthController::login');
$routes->match(['GET', 'POST'], 'logout', 'Auth\AuthController::logout');

$routes->get('cp/password-reset', 'Auth\PasswordResetController::requestForm');
$routes->post('cp/password-reset', 'Auth\PasswordResetController::requestSubmit');
$routes->get('cp/password-reset/verify', 'Auth\PasswordResetController::verifyForm');
$routes->post('cp/password-reset/verify', 'Auth\PasswordResetController::verifySubmit');
$routes->match(['GET', 'POST'], 'cp/admin-recovery', 'Auth\AdminRecoveryController::recover');

$routes->group('cp', ['filter' => 'session'], static function ($routes): void {
    $routes->get('password-change', 'Auth\PasswordChangeController::show');
    $routes->post('password-change', 'Auth\PasswordChangeController::submit');
});

/*
 |--------------------------------------------------------------------
 | Public Post rendering (Phase 3 / Task 3.9 / ADR-016)
 |--------------------------------------------------------------------
 | Fixed developer routes — not Admin-configurable.
 | Primary (id): /news/{slug}
 | Secondary (en): /en/news/{slug}
 | No /news listing. No session/group/permission filters.
 */
$routes->get('news/(:segment)', 'Site\PostController::show/$1', ['filter' => 'publicLocale']);
$routes->get('en/news/(:segment)', 'Site\PostController::showEn/$1', ['filter' => 'publicLocale']);

/*
 |--------------------------------------------------------------------
 | Controlled document download (ADR-007 / ADR-018)
 |--------------------------------------------------------------------
 | MUST remain before Page catch-all. No session auth — token is the
 | capability; ACTIVE DOCUMENT only resolved in MediaService.
 */
$routes->get('download/document/(:segment)', 'Site\DocumentDownloadController::document/$1');

/*
 |--------------------------------------------------------------------
 | Public SEO endpoints (Phase 7 / Task 7.1B / ADR-024)
 |--------------------------------------------------------------------
 */
$routes->get('sitemap.xml', 'Site\SitemapController::index', ['filter' => 'publicLocale']);
$routes->get('robots.txt', 'Site\RobotsController::index', ['filter' => 'publicLocale']);

/*
 |--------------------------------------------------------------------
 | Authenticated Control Panel shell (Phase 1 / Tasks 1.17–1.18)
 |--------------------------------------------------------------------
 | Protected by:
 |   1. Shield SessionAuth (`session`) — must be authenticated
 |   2. Shield GroupFilter (`group:admin,editor,contributor`) — staff only
 |
 | Unauthenticated visitors → named route `login` → /cp.
 | Authenticated non-staff → Auth.group_denied redirect (Shield default).
 */

$routes->group(
    'admin',
    ['filter' => ['session', 'force-reset', 'group:admin,editor,contributor']],
    static function ($routes): void {
        $routes->get('/', 'Admin\AdminController::index');

        /*
         | Audit Trail — Admin only via permission:audit.view (DOC-03 AUTHZ-007 / ADR-019).
         | Read-only GET. Viewing does not append audit rows.
         */
        $routes->get('audit', 'Admin\AuditController::index', ['filter' => 'permission:audit.view']);

        /*
         | Site Settings — Admin only via permission:site.manage (DOC-03 AUTHZ-006).
         */
        $routes->match(
            ['GET', 'POST'],
            'settings',
            'Admin\SettingsController::index',
            ['filter' => 'permission:site.manage'],
        );

        /*
         | Themes — Admin activation via permission:theme.activate (ADR-022 / DOC-03 AUTHZ-005).
         */
        $routes->get('themes', 'Admin\ThemeController::index', ['filter' => 'permission:theme.activate']);
        $routes->post('themes/(:segment)/activate', 'Admin\ThemeController::activate/$1', ['filter' => 'permission:theme.activate']);

        /*
         | Theme Preview — Admin only via permission:theme.preview (ADR-023 / DOC-03).
         | GET-only; request-scoped candidate Theme; no Settings or content mutation.
         */
        $routes->get(
            'preview/theme/(:segment)/page/(:num)',
            'Admin\ThemePreviewController::show/$1/$2',
            ['filter' => 'permission:theme.preview'],
        );

        /*
         | Menus — Admin only via permission:menu.manage (DOC-03 / REQ-MENU-006).
         */
        $routes->group('menus', ['filter' => 'permission:menu.manage'], static function ($routes): void {
            $routes->get('/', 'Admin\MenuController::index');
            $routes->get('new', 'Admin\MenuController::create');
            $routes->post('/', 'Admin\MenuController::store');
            $routes->get('(:num)/edit', 'Admin\MenuController::edit/$1');
            $routes->post('(:num)', 'Admin\MenuController::update/$1');
            $routes->post('(:num)/delete', 'Admin\MenuController::delete/$1');
        });

        /*
         | Pages — DOC-03 page.create / page.edit / page.trash (REQ-PAGE-*).
         */
        $routes->get('pages', 'Admin\PageController::index', ['filter' => 'permission:page.edit']);
        $routes->get('pages/new', 'Admin\PageController::create', ['filter' => 'permission:page.create']);
        $routes->post('pages', 'Admin\PageController::store', ['filter' => 'permission:page.create']);
        $routes->get('pages/(:num)/edit', 'Admin\PageController::edit/$1', ['filter' => 'permission:page.edit']);
        $routes->post('pages/(:num)', 'Admin\PageController::update/$1', ['filter' => 'permission:page.edit']);
        $routes->post('pages/(:num)/publish', 'Admin\PageController::publish/$1', ['filter' => 'permission:page.publish']);
        $routes->post('pages/(:num)/unpublish', 'Admin\PageController::unpublish/$1', ['filter' => 'permission:page.unpublish']);
        $routes->post('pages/(:num)/archive', 'Admin\PageController::archive/$1', ['filter' => 'permission:page.archive']);
        $routes->post('pages/(:num)/delete', 'Admin\PageController::delete/$1', ['filter' => 'permission:page.trash']);
        $routes->post('pages/(:num)/restore', 'Admin\PageController::restore/$1', ['filter' => 'permission:page.restore']);
        $routes->post('pages/(:num)/permanent-delete', 'Admin\PageController::permanentDelete/$1', ['filter' => 'permission:content.permanent_delete']);
        $routes->get('pages/(:num)/revisions', 'Admin\PageController::revisions/$1', ['filter' => 'permission:page.edit']);
        $routes->post(
            'pages/(:num)/revisions/(:num)/restore',
            'Admin\PageController::restoreRevision/$1/$2',
            ['filter' => 'permission:page.restore'],
        );
        $routes->post('pages/(:num)/autosave', 'Admin\PageController::autosave/$1', ['filter' => 'permission:page.edit']);
        $routes->post('pages/(:num)/schedules', 'Admin\PageController::storeSchedule/$1');
        $routes->post('pages/(:num)/schedules/(:num)/cancel', 'Admin\PageController::cancelSchedule/$1/$2');

        /*
         | Posts — DOC-03 post.create / post.edit_* (Service ownership) / post.trash.
         | Index/edit use group boundary; write authorization enforced in PostService (AUTHZ-001).
         | Revision history: Editor/Admin only (ADR-019 — Contributor excluded via post.edit_any).
         */
        $routes->get('posts', 'Admin\PostController::index', ['filter' => 'permission:post.create']);
        $routes->get('posts/new', 'Admin\PostController::create', ['filter' => 'permission:post.create']);
        $routes->post('posts', 'Admin\PostController::store', ['filter' => 'permission:post.create']);
        $routes->get('posts/(:num)/edit', 'Admin\PostController::edit/$1', ['filter' => 'permission:post.create']);
        $routes->post('posts/(:num)', 'Admin\PostController::update/$1', ['filter' => 'permission:post.create']);
        $routes->post('posts/(:num)/publish', 'Admin\PostController::publish/$1', ['filter' => 'permission:post.publish']);
        $routes->post('posts/(:num)/unpublish', 'Admin\PostController::unpublish/$1', ['filter' => 'permission:post.unpublish']);
        $routes->post('posts/(:num)/archive', 'Admin\PostController::archive/$1', ['filter' => 'permission:post.archive']);
        $routes->post('posts/(:num)/submit-review', 'Admin\PostController::submitForReview/$1', ['filter' => 'permission:post.submit_review']);
        $routes->post('posts/(:num)/review-publish', 'Admin\PostController::reviewAndPublish/$1', ['filter' => 'permission:post.review']);
        $routes->post('posts/(:num)/return-revision', 'Admin\PostController::returnForRevision/$1', ['filter' => 'permission:post.review']);
        $routes->post('posts/(:num)/delete', 'Admin\PostController::delete/$1', ['filter' => 'permission:post.trash']);
        $routes->post('posts/(:num)/restore', 'Admin\PostController::restore/$1', ['filter' => 'permission:post.restore']);
        $routes->post('posts/(:num)/permanent-delete', 'Admin\PostController::permanentDelete/$1', ['filter' => 'permission:content.permanent_delete']);
        $routes->get('posts/(:num)/revisions', 'Admin\PostController::revisions/$1', ['filter' => 'permission:post.edit_any']);
        $routes->post(
            'posts/(:num)/revisions/(:num)/restore',
            'Admin\PostController::restoreRevision/$1/$2',
            ['filter' => 'permission:post.restore'],
        );
        // Same edit boundary as POST update; ownership enforced in PostService::autosave.
        $routes->post('posts/(:num)/autosave', 'Admin\PostController::autosave/$1', ['filter' => 'permission:post.create']);
        $routes->post('posts/(:num)/schedules', 'Admin\PostController::storeSchedule/$1');
        $routes->post('posts/(:num)/schedules/(:num)/cancel', 'Admin\PostController::cancelSchedule/$1/$2');

        /*
         | Users — V2-003 user.manage (ADR-027 P0-1).
         */
        $routes->group('users', ['filter' => 'permission:user.manage'], static function ($routes): void {
            $routes->get('/', 'Admin\UserController::index');
            $routes->get('new', 'Admin\UserController::create');
            $routes->post('/', 'Admin\UserController::store');
            $routes->get('(:num)/edit', 'Admin\UserController::edit/$1');
            $routes->post('(:num)', 'Admin\UserController::update/$1');
            $routes->post('(:num)/activate', 'Admin\UserController::activate/$1');
            $routes->post('(:num)/deactivate', 'Admin\UserController::deactivate/$1');
        });

        /*
         | Categories — DOC-03 category.manage (REQ-CAT-002).
         */
        $routes->group('categories', ['filter' => 'permission:category.manage'], static function ($routes): void {
            $routes->get('/', 'Admin\CategoryController::index');
            $routes->get('new', 'Admin\CategoryController::create');
            $routes->post('/', 'Admin\CategoryController::store');
            $routes->get('(:num)/edit', 'Admin\CategoryController::edit/$1');
            $routes->post('(:num)', 'Admin\CategoryController::update/$1');
            $routes->post('(:num)/deactivate', 'Admin\CategoryController::deactivate/$1');
            $routes->post('(:num)/restore', 'Admin\CategoryController::restore/$1');
        });

        /*
         | Tags — DOC-03 tag.manage (REQ-TAG-002).
         */
        $routes->group('tags', ['filter' => 'permission:tag.manage'], static function ($routes): void {
            $routes->get('/', 'Admin\TagController::index');
            $routes->get('new', 'Admin\TagController::create');
            $routes->post('/', 'Admin\TagController::store');
            $routes->get('(:num)/edit', 'Admin\TagController::edit/$1');
            $routes->post('(:num)', 'Admin\TagController::update/$1');
        });

        /*
         | Media Library — DOC-03 media.* / ADR-018.
         */
        $routes->get('media', 'Admin\MediaController::index', ['filter' => 'permission:media.upload']);
        $routes->get('media/upload', 'Admin\MediaController::uploadForm', ['filter' => 'permission:media.upload']);
        $routes->get('media/picker', 'Admin\MediaController::picker', ['filter' => 'permission:media.upload']);
        $routes->post('media', 'Admin\MediaController::store', ['filter' => 'permission:media.upload']);
        $routes->get('media/(:num)/edit', 'Admin\MediaController::edit/$1', ['filter' => 'permission:media.edit']);
        $routes->post('media/(:num)', 'Admin\MediaController::update/$1', ['filter' => 'permission:media.edit']);
        $routes->post('media/(:num)/trash', 'Admin\MediaController::trash/$1', ['filter' => 'permission:media.delete']);
        $routes->post('media/(:num)/restore', 'Admin\MediaController::restore/$1', ['filter' => 'permission:media.restore']);
        $routes->post('media/(:num)/delete', 'Admin\MediaController::delete/$1', ['filter' => 'permission:content.permanent_delete']);
    },
);

/*
 |--------------------------------------------------------------------
 | Public Page rendering (Phase 4 / Task 4.4 / ADR-017)
 |--------------------------------------------------------------------
 | Primary (id): /{slug}
 | Secondary (en): /en/{slug}
 | Single-segment only — hierarchy does not nest paths.
 | Registered AFTER Post /news routes and /admin|/cp|/logout so those
 | namespaces are never captured by the Page catch-all.
 | No session/group/permission filters. No /id/{slug}. No listing.
 */
$routes->get('en/(:segment)', 'Site\PageController::showEn/$1', ['filter' => 'publicLocale']);
$routes->get('(:segment)', 'Site\PageController::show/$1', ['filter' => 'publicLocale']);
