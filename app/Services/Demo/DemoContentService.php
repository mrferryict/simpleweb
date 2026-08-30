<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Dtos\PageWriteDto;
use App\Dtos\PostWriteDto;
use App\Enums\PageStatus;
use App\Enums\PostStatus;
use App\Models\PageTranslationModel;
use App\Models\PostTranslationModel;
use App\Services\Install\InstallService;
use App\Services\PageService;
use App\Services\PostService;
use App\Services\SettingService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use RuntimeException;

/**
 * Optional starter content for `cms:demo` (post-V1 / TH-004).
 *
 * Creates generic Pages and a Post through domain Services only.
 * Idempotent by known slug + primary locale; never overwrites existing content.
 */
final class DemoContentService
{
    public const ALREADY_INSTALLED_MESSAGE = 'SMITE CMS demo content is already installed. No changes made.';

    /** News landing uses a non-reserved slug; Post URLs remain under /news/{slug} (ADR-024). */
    public const DEMO_NEWS_LANDING_SLUG = 'berita';

    /** @var list<string> */
    public const DEMO_PAGE_SLUGS = ['about', 'contact', self::DEMO_NEWS_LANDING_SLUG];

    public const DEMO_POST_SLUG = 'welcome';

    public function __construct(
        private readonly PageService $pageService,
        private readonly PostService $postService,
        private readonly PageTranslationModel $pageTranslationModel,
        private readonly PostTranslationModel $postTranslationModel,
        private readonly SettingService $settingService,
        private readonly InstallService $installService,
        private readonly BaseConnection $db,
    ) {
    }

    /**
     * @return array{
     *     status: 'installed'|'already_installed',
     *     pages_created: int,
     *     posts_created: int,
     *     skipped: list<string>,
     *     message: string
     * }
     */
    public function install(): array
    {
        if (! $this->installService->adminExists()) {
            throw new RuntimeException(
                'SMITE CMS must be installed before demo content can be added.',
            );
        }

        $locale = $this->settingService->primaryLocale();
        $actor  = $this->resolveAdminUser();

        $pagesCreated = 0;
        $postsCreated = 0;
        $skipped      = [];

        foreach ($this->demoPageDefinitions() as $definition) {
            $slug = $definition['slug'];
            if ($this->pageSlugExists($slug, $locale)) {
                $skipped[] = 'page:' . $slug;

                continue;
            }

            $this->createAndPublishPage(
                new PageWriteDto(
                    title: $definition['title'],
                    slug: $slug,
                    locale: $locale,
                    templateKey: 'custom-page',
                    parentId: null,
                    contentPayload: $definition['content_payload'],
                ),
                $actor,
            );
            $pagesCreated++;
        }

        if ($this->postSlugExists(self::DEMO_POST_SLUG, $locale)) {
            $skipped[] = 'post:' . self::DEMO_POST_SLUG;
        } else {
            $postDefinition = $this->demoPostDefinition();
            $this->createAndPublishPost(
                new PostWriteDto(
                    title: $postDefinition['title'],
                    slug: self::DEMO_POST_SLUG,
                    locale: $locale,
                    manualAuthor: $postDefinition['manual_author'],
                    contentPayload: $postDefinition['content_payload'],
                    createdBy: (int) $actor->id,
                ),
                $actor,
            );
            $postsCreated++;
        }

        if ($pagesCreated === 0 && $postsCreated === 0) {
            return [
                'status'         => 'already_installed',
                'pages_created'  => 0,
                'posts_created'  => 0,
                'skipped'        => $skipped,
                'message'        => self::ALREADY_INSTALLED_MESSAGE,
            ];
        }

        return [
            'status'        => 'installed',
            'pages_created' => $pagesCreated,
            'posts_created' => $postsCreated,
            'skipped'       => $skipped,
            'message'       => 'SMITE CMS demo content installed.',
        ];
    }

    /**
     * @return list<array{
     *     slug: string,
     *     title: string,
     *     content_payload: array<string, mixed>
     * }>
     */
    private function demoPageDefinitions(): array
    {
        return [
            [
                'slug'            => 'about',
                'title'           => 'About',
                'content_payload' => [
                    'hero_title' => 'Tentang Kami',
                    'body'       => '<p>Halaman ini merupakan halaman contoh yang dapat Anda ubah sesuai identitas organisasi Anda.</p>'
                        . '<p>Gunakan area ini untuk memperkenalkan tujuan, nilai, dan informasi penting bagi pengunjung.</p>',
                ],
            ],
            [
                'slug'            => 'contact',
                'title'           => 'Contact',
                'content_payload' => [
                    'hero_title' => 'Hubungi Kami',
                    'body'       => '<p>Gunakan halaman ini untuk menampilkan informasi kontak organisasi Anda.</p>'
                        . '<p>Anda dapat menambahkan detail kontak melalui Control Panel setelah konten siap dipublikasikan.</p>',
                ],
            ],
            [
                'slug'            => self::DEMO_NEWS_LANDING_SLUG,
                'title'           => 'News',
                'content_payload' => [
                    'hero_title' => 'Berita',
                    'body'       => '<p>Temukan berita, informasi, dan pengumuman terbaru melalui website ini.</p>'
                        . '<p>Artikel individual dipublikasikan di bawah jalur <code>/news/...</code>. '
                        . 'Anda dapat menambahkan dan mengelola berita melalui Control Panel.</p>',
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     manual_author: string,
     *     content_payload: array<string, mixed>
     * }
     */
    private function demoPostDefinition(): array
    {
        return [
            'title'           => 'Welcome to SMITE CMS',
            'manual_author'   => 'SMITE CMS',
            'content_payload' => [
                'body' => '<p>Selamat datang di situs Anda. Halaman dan berita ini adalah contoh konten awal yang dapat disesuaikan melalui Control Panel.</p>'
                    . '<p>Perbarui teks, tambahkan halaman baru, dan publikasikan berita sesuai kebutuhan organisasi Anda.</p>',
            ],
        ];
    }

    private function pageSlugExists(string $slug, string $locale): bool
    {
        return $this->pageTranslationModel->findBySlugAndLocale($slug, $locale) !== null;
    }

    private function postSlugExists(string $slug, string $locale): bool
    {
        return $this->postTranslationModel->findBySlugAndLocale($slug, $locale) !== null;
    }

    private function createAndPublishPage(PageWriteDto $dto, User $actor): void
    {
        $errors = $this->pageService->create($dto, $actor);
        if ($errors !== []) {
            throw new RuntimeException($this->formatServiceErrors('Page', $errors));
        }

        $translation = $this->pageTranslationModel->findBySlugAndLocale($dto->slug, $dto->locale);
        if ($translation === null) {
            throw new RuntimeException('Unable to resolve created demo Page.');
        }

        $pageId = (int) $translation->page_id;
        $errors = $this->pageService->publish($pageId, $actor);
        if ($errors !== []) {
            throw new RuntimeException($this->formatServiceErrors('Page publish', $errors));
        }
    }

    private function createAndPublishPost(PostWriteDto $dto, User $actor): void
    {
        $errors = $this->postService->create($dto, $actor);
        if ($errors !== []) {
            throw new RuntimeException($this->formatServiceErrors('Post', $errors));
        }

        $translation = $this->postTranslationModel->findBySlugAndLocale($dto->slug, $dto->locale);
        if ($translation === null) {
            throw new RuntimeException('Unable to resolve created demo Post.');
        }

        $postId = (int) $translation->post_id;
        $errors = $this->postService->publish($postId, $actor);
        if ($errors !== []) {
            throw new RuntimeException($this->formatServiceErrors('Post publish', $errors));
        }
    }

    private function resolveAdminUser(): User
    {
        if (! $this->db->tableExists('auth_groups_users') || ! $this->db->tableExists('users')) {
            throw new RuntimeException(
                'SMITE CMS must be installed before demo content can be added.',
            );
        }

        $row = $this->db->table('auth_groups_users')
            ->select('user_id')
            ->where('group', 'admin')
            ->orderBy('user_id', 'ASC')
            ->get()
            ->getRowArray();

        if ($row === null || ! isset($row['user_id'])) {
            throw new RuntimeException(
                'SMITE CMS must be installed before demo content can be added.',
            );
        }

        /** @var UserModel $users */
        $users = model(UserModel::class);
        $user  = $users->find((int) $row['user_id']);

        if (! $user instanceof User) {
            throw new RuntimeException('Unable to resolve the initial Admin user.');
        }

        return $user;
    }

    /**
     * @param array<string, string> $errors
     */
    private function formatServiceErrors(string $context, array $errors): string
    {
        $parts = [];
        foreach ($errors as $field => $message) {
            $parts[] = $field . ': ' . $message;
        }

        return $context . ' demo content failed: ' . implode('; ', $parts);
    }
}
