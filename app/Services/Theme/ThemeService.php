<?php

declare(strict_types=1);

namespace App\Services\Theme;

use App\Enums\AuditEvent;
use App\Enums\ThemeState;
use App\Services\Audit\AuditService;
use App\Services\Cache\PublicContentCacheInvalidator;
use App\Services\SettingService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use Config\Theme as ThemeConfig;
use RuntimeException;

/**
 * Theme discovery, Manifest resolution, and lifecycle persistence (ADR-002 / ADR-022).
 *
 * Public template view paths follow ADR-016. Preview remains deferred.
 * Image Profiles: DOC-05 §8 / DOC-06 §11 / ADR-018 §12 (`cms_default` baseline).
 */
class ThemeService
{
    /** Built-in / baseline Image Profile id (ADR-018 §12). */
    public const BASELINE_IMAGE_PROFILE_ID = 'cms_default';

    private const BASELINE_MAX_DIMENSION = 2560;

    private const BASELINE_MAX_FILE_SIZE = 5_242_880; // 5 MiB

    /** @var list<string> */
    private const BASELINE_ALLOWED_FORMATS = ['jpeg', 'jpg', 'png', 'webp', 'gif'];

    private const THEME_ID_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** @var array<string, array<string, mixed>> */
    private array $manifestCache = [];

    public function __construct(
        private readonly ThemeConfig $themeConfig,
        private readonly SettingService $settingService,
        private readonly AuditService $auditService,
        private readonly PublicContentCacheInvalidator $publicContentCacheInvalidator,
        private readonly BaseConnection $db,
    ) {
    }

    /**
     * Resolved ACTIVE Theme id (Settings first, Config bootstrap fallback).
     */
    public function activeThemeId(): string
    {
        return $this->resolveActiveThemeId();
    }

    /**
     * Discover valid Theme ids from immediate child directories of app/Views/themes/.
     *
     * @return list<string>
     */
    public function discoverThemeIds(): array
    {
        $root = APPPATH . 'Views/themes/';
        if (! is_dir($root)) {
            return [];
        }

        $ids = [];
        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $dirPath = $root . $entry;
            if (! is_dir($dirPath)) {
                continue;
            }

            if (! is_file($dirPath . '/ThemeManifest.php')) {
                continue;
            }

            if (! $this->isValidThemeIdentifier($entry)) {
                continue;
            }

            if (! $this->manifestDirectoryMatchesId($entry)) {
                continue;
            }

            $ids[] = $entry;
        }

        sort($ids);

        return $ids;
    }

    /**
     * ENABLED Themes for Admin listing (DRAFT Themes excluded).
     *
     * @return list<array{
     *     id: string,
     *     name: string,
     *     version: string,
     *     author: string,
     *     state: string,
     *     is_active: bool
     * }>
     */
    public function listEnabledThemesForAdmin(): array
    {
        $activeId = $this->resolveActiveThemeId();
        $out      = [];

        foreach ($this->normalizedEnabledThemeIds() as $enabledId) {
            if ($this->validateActivationCandidate($enabledId) !== []) {
                continue;
            }

            $manifest = $this->loadManifestFor($enabledId);
            $state    = $enabledId === $activeId ? ThemeState::Active : ThemeState::Enabled;

            $out[] = [
                'id'        => $enabledId,
                'name'      => $manifest['name'],
                'version'   => $manifest['version'],
                'author'    => $manifest['author'],
                'state'     => $state->value,
                'is_active' => $enabledId === $activeId,
            ];
        }

        return $out;
    }

    /**
     * Lifecycle state for a discovered Theme id, or null when not discovered.
     */
    public function themeState(string $themeId): ?ThemeState
    {
        $id = strtolower(trim($themeId));
        if (! in_array($id, $this->discoverThemeIds(), true)) {
            return null;
        }

        if (! $this->isEnabled($id)) {
            return ThemeState::Draft;
        }

        try {
            if ($id === $this->resolveActiveThemeId()) {
                return ThemeState::Active;
            }
        } catch (RuntimeException) {
            return ThemeState::Enabled;
        }

        return ThemeState::Enabled;
    }

    /**
     * Validate an activation candidate (ADR-022 §11).
     *
     * @return array<string, string> Field-keyed errors; empty when valid.
     */
    public function validateActivationCandidate(string $themeId): array
    {
        $id = strtolower(trim($themeId));
        if (! $this->isValidThemeIdentifier($id)) {
            return ['theme_id' => 'Theme identifier format is invalid.'];
        }

        if (! $this->isEnabled($id)) {
            return ['theme_id' => 'Theme is not enabled.'];
        }

        if (! in_array($id, $this->discoverThemeIds(), true)) {
            return ['theme_id' => 'Theme could not be discovered.'];
        }

        $path = $this->manifestPathFor($id);
        if (! is_file($path)) {
            return ['theme_id' => 'Theme manifest is missing.'];
        }

        try {
            $manifest = $this->loadManifestFor($id);
        } catch (RuntimeException) {
            return ['theme_id' => 'Theme manifest could not be loaded.'];
        }

        if (strtolower(trim($manifest['id'])) !== $id) {
            return ['theme_id' => 'Theme manifest id does not match directory name.'];
        }

        $errors = $this->validateManifestStructure($manifest);
        if ($errors !== []) {
            return ['theme_id' => 'Theme manifest structure is invalid.'];
        }

        foreach (['custom-page', 'custom-post'] as $templateKey) {
            $templatePath = APPPATH . 'Views/themes/' . $id . '/templates/' . $templateKey . '.php';
            if (! is_file($templatePath)) {
                return ['theme_id' => 'Required Theme template file is missing.'];
            }
        }

        return [];
    }

    /**
     * Validate a Theme Preview candidate (ADR-023).
     *
     * Reuses activation validation; Preview additionally requires `theme.preview`.
     *
     * @return array<string, string> Field-keyed errors; empty when valid.
     */
    public function validatePreviewCandidate(string $themeId, ?User $actor = null): array
    {
        if ($actor !== null && ! $actor->can('theme.preview')) {
            return ['_forbidden' => 'You are not allowed to preview Themes.'];
        }

        return $this->validateActivationCandidate($themeId);
    }

    /**
     * Activate an ENABLED Theme (ADR-022). Service-layer authorization is authoritative.
     *
     * @return array<string, string> Errors; empty on success.
     */
    #[\NoDiscard]
    public function activate(string $themeId, ?User $actor = null): array
    {
        if ($actor !== null && ! $actor->can('theme.activate')) {
            return ['_forbidden' => 'You are not allowed to activate Themes.'];
        }

        $id = strtolower(trim($themeId));
        $errors = $this->validateActivationCandidate($id);
        if ($errors !== []) {
            return $errors;
        }

        try {
            $previousActive = $this->resolveActiveThemeId();
        } catch (RuntimeException) {
            $previousActive = null;
        }

        if ($previousActive === $id) {
            return [];
        }

        $this->db->transStart();

        $this->settingService->setPersistedActiveThemeId($id);

        (void) $this->auditService->append(
            AuditEvent::ThemeActivated,
            $this->actorId($actor),
            'theme',
            null,
            null,
            [
                'previous_theme_id' => $previousActive,
                'new_theme_id'      => $id,
            ],
        );

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to activate Theme.'];
        }

        $this->clearManifestCache();
        $this->publicContentCacheInvalidator->invalidateThemePresentation();

        return [];
    }

    /**
     * Image Profiles declared on the active Theme Manifest (may be empty).
     *
     * @return array<string, array<string, mixed>>
     */
    public function mediaProfiles(): array
    {
        $manifest = $this->loadActiveManifest();
        $profiles = $manifest['media_profiles'] ?? [];
        if (! is_array($profiles)) {
            return [];
        }

        $out = [];
        foreach ($profiles as $profileId => $definition) {
            if (! is_string($profileId) || $profileId === '' || ! is_array($definition)) {
                continue;
            }
            $out[$profileId] = $definition;
        }

        return $out;
    }

    /**
     * Resolve an Image Profile for upload/validation (ADR-018 §12).
     *
     * @return array{
     *     id: string,
     *     maximum_width: int,
     *     maximum_height: int,
     *     maximum_file_size: int,
     *     allowed_formats: list<string>,
     *     minimum_width: int|null,
     *     minimum_height: int|null
     * }
     */
    public function resolveImageProfile(?string $profileId = null): array
    {
        $id = strtolower(trim((string) ($profileId ?? '')));
        if ($id === '') {
            $id = self::BASELINE_IMAGE_PROFILE_ID;
        }

        $catalog = $this->mediaProfiles();
        if ($catalog !== [] && isset($catalog[$id]) && is_array($catalog[$id])) {
            return $this->normalizeImageProfile($id, $catalog[$id]);
        }

        return $this->builtInCmsDefaultProfile();
    }

    /**
     * Absolute filesystem path to the active theme's ThemeManifest.php.
     */
    public function activeManifestPath(): string
    {
        return $this->manifestPathFor($this->activeThemeId());
    }

    /**
     * Load and structurally validate the active Theme Manifest.
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     version: string,
     *     author: string,
     *     media_profiles: array<string, mixed>,
     *     templates: array<string, array{label?: string, fields: array<string, array<string, mixed>>}>
     * }
     */
    #[\NoDiscard]
    public function loadActiveManifest(): array
    {
        return $this->loadManifestFor($this->activeThemeId());
    }

    /**
     * Whether the active theme declares the given template key.
     */
    public function hasTemplate(string $templateKey): bool
    {
        $key = trim($templateKey);
        if ($key === '') {
            return false;
        }

        $manifest = $this->loadActiveManifest();

        return array_key_exists($key, $manifest['templates']);
    }

    /**
     * Resolve Content Schema field map for a template key on the active theme.
     *
     * @return array<string, array<string, mixed>>
     */
    #[\NoDiscard]
    public function contentSchemaForTemplate(string $templateKey): array
    {
        $key = trim($templateKey);
        if ($key === '' || ! $this->hasTemplate($key)) {
            throw new RuntimeException('Template is not available on the active theme.');
        }

        $manifest = $this->loadActiveManifest();
        $fields   = $manifest['templates'][$key]['fields'] ?? null;
        if (! is_array($fields)) {
            throw new RuntimeException('Template content schema is invalid.');
        }

        /** @var array<string, array<string, mixed>> $fields */
        return $fields;
    }

    /**
     * CI4 view name for a public Theme template (ADR-016).
     */
    #[\NoDiscard]
    public function publicViewNameForTemplate(string $templateKey): string
    {
        $key = $this->assertPublicTemplateKey($templateKey);
        $path = $this->publicViewPathForTemplate($key);
        if (! is_file($path)) {
            throw new RuntimeException('Theme public template view is missing.');
        }

        return 'themes/' . $this->activeThemeId() . '/templates/' . $key;
    }

    /**
     * Whether a candidate Theme declares the given template key (request-scoped).
     */
    public function hasTemplateOnTheme(string $themeId, string $templateKey): bool
    {
        $id  = strtolower(trim($themeId));
        $key = trim($templateKey);
        if ($key === '' || ! $this->isValidThemeIdentifier($id)) {
            return false;
        }

        try {
            $manifest = $this->loadManifestFor($id);
        } catch (RuntimeException) {
            return false;
        }

        return array_key_exists($key, $manifest['templates']);
    }

    /**
     * Content Schema for a template on a request-scoped candidate Theme (ADR-023).
     *
     * @return array<string, array<string, mixed>>
     */
    #[\NoDiscard]
    public function contentSchemaForThemeTemplate(string $themeId, string $templateKey): array
    {
        $id  = strtolower(trim($themeId));
        $key = trim($templateKey);
        if ($key === '' || ! $this->hasTemplateOnTheme($id, $key)) {
            throw new RuntimeException('Template is not available on the selected theme.');
        }

        $manifest = $this->loadManifestFor($id);
        $fields   = $manifest['templates'][$key]['fields'] ?? null;
        if (! is_array($fields)) {
            throw new RuntimeException('Template content schema is invalid.');
        }

        /** @var array<string, array<string, mixed>> $fields */
        return $fields;
    }

    /**
     * CI4 view name for a Theme template on a request-scoped candidate Theme (ADR-023).
     */
    #[\NoDiscard]
    public function publicViewNameForThemeTemplate(string $themeId, string $templateKey): string
    {
        $id  = strtolower(trim($themeId));
        $key = trim($templateKey);
        if ($key === '' || ! $this->hasTemplateOnTheme($id, $key)) {
            throw new RuntimeException('Template is not available on the selected theme.');
        }

        $path = APPPATH . 'Views/themes/' . $id . '/templates/' . $key . '.php';
        if (! is_file($path)) {
            throw new RuntimeException('Theme public template view is missing.');
        }

        return 'themes/' . $id . '/templates/' . $key;
    }

    /**
     * Absolute filesystem path for a public Theme template view (ADR-016).
     */
    public function publicViewPathForTemplate(string $templateKey): string
    {
        $key = trim($templateKey);
        if ($key === '' || ! preg_match(self::THEME_ID_PATTERN, $key)) {
            throw new RuntimeException('Template is not available on the active theme.');
        }

        return APPPATH . 'Views/themes/' . $this->activeThemeId() . '/templates/' . $key . '.php';
    }

    /**
     * Structural validation of a Theme Manifest array (ADR-002 / DOC-05 §8).
     *
     * @param array<mixed> $manifest
     *
     * @return array<string, string> Field-keyed errors; empty when valid.
     */
    public function validateManifestStructure(array $manifest): array
    {
        $errors = [];

        foreach (['id', 'name', 'version', 'author'] as $key) {
            if (! isset($manifest[$key]) || ! is_string($manifest[$key]) || trim($manifest[$key]) === '') {
                $errors[$key] = 'Theme manifest metadata is incomplete.';
            }
        }

        if (isset($manifest['id'], $manifest['version'])
            && is_string($manifest['id'])
            && is_string($manifest['version'])
        ) {
            if (! preg_match(self::THEME_ID_PATTERN, $manifest['id'])) {
                $errors['id'] = 'Theme id format is invalid.';
            }
            if (trim($manifest['version']) === '') {
                $errors['version'] = 'Theme version is required.';
            }
        }

        if (! isset($manifest['templates']) || ! is_array($manifest['templates']) || $manifest['templates'] === []) {
            $errors['templates'] = 'Theme manifest templates are required.';

            return $errors;
        }

        if (! array_key_exists('custom-page', $manifest['templates'])) {
            $errors['custom-page'] = 'Theme manifest must declare the custom-page template.';
        }

        if (! array_key_exists('custom-post', $manifest['templates'])) {
            $errors['custom-post'] = 'Theme manifest must declare the custom-post template.';
        }

        if (! array_key_exists('media_profiles', $manifest) || ! is_array($manifest['media_profiles'])) {
            $errors['media_profiles'] = 'Theme manifest media_profiles must be an array.';
        }

        foreach ($manifest['templates'] as $templateKey => $template) {
            if (! is_string($templateKey) || $templateKey === '') {
                $errors['templates'] = 'Template keys must be non-empty strings.';

                continue;
            }

            if (! is_array($template)) {
                $errors[$templateKey] = 'Template declaration must be an array.';

                continue;
            }

            if (! isset($template['fields']) || ! is_array($template['fields'])) {
                $errors[$templateKey] = 'Template content schema fields are required.';

                continue;
            }

            foreach ($template['fields'] as $fieldKey => $definition) {
                if (! is_string($fieldKey) || $fieldKey === '' || ! is_array($definition)) {
                    $errors[$templateKey . '.' . (string) $fieldKey] = 'Content field definition is invalid.';

                    continue;
                }

                if (! isset($definition['type']) || ! is_string($definition['type']) || $definition['type'] === '') {
                    $errors[$templateKey . '.' . $fieldKey] = 'Content field type is required.';
                }
            }
        }

        return $errors;
    }

    public function isValidThemeIdentifier(string $themeId): bool
    {
        $id = trim($themeId);

        return $id !== '' && preg_match(self::THEME_ID_PATTERN, $id) === 1;
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     version: string,
     *     author: string,
     *     media_profiles: array<string, mixed>,
     *     templates: array<string, array{label?: string, fields: array<string, array<string, mixed>>}>
     * }
     */
    public function loadManifestFor(string $themeId): array
    {
        $id = strtolower(trim($themeId));
        if (isset($this->manifestCache[$id])) {
            /** @var array{
             *     id: string,
             *     name: string,
             *     version: string,
             *     author: string,
             *     media_profiles: array<string, mixed>,
             *     templates: array<string, array{label?: string, fields: array<string, array<string, mixed>>}>
             * } $cached
             */
            return $this->manifestCache[$id];
        }

        $path = $this->manifestPathFor($id);
        if (! is_file($path)) {
            throw new RuntimeException('Theme manifest is missing.');
        }

        /** @var mixed $manifest */
        $manifest = require $path;
        if (! is_array($manifest)) {
            throw new RuntimeException('Theme manifest must return a PHP array.');
        }

        $errors = $this->validateManifestStructure($manifest);
        if ($errors !== []) {
            throw new RuntimeException('Theme manifest is invalid.');
        }

        /** @var array{
         *     id: string,
         *     name: string,
         *     version: string,
         *     author: string,
         *     media_profiles: array<string, mixed>,
         *     templates: array<string, array{label?: string, fields: array<string, array<string, mixed>>}>
         * } $manifest
         */
        $this->manifestCache[$id] = $manifest;

        return $manifest;
    }

    private function resolveActiveThemeId(): string
    {
        $persisted = $this->settingService->getPersistedActiveThemeId();
        if ($persisted !== null) {
            if ($this->validateActivationCandidate($persisted) !== []) {
                throw new RuntimeException('Persisted active Theme is invalid.');
            }

            return $persisted;
        }

        return $this->bootstrapActiveThemeId();
    }

    private function bootstrapActiveThemeId(): string
    {
        $bootstrap = strtolower(trim($this->themeConfig->activeThemeId));
        if ($bootstrap === '') {
            $bootstrap = 'default';
        }

        if ($this->validateActivationCandidate($bootstrap) !== []) {
            throw new RuntimeException('Bootstrap active Theme is invalid.');
        }

        return $bootstrap;
    }

    /**
     * @return list<string>
     */
    private function normalizedEnabledThemeIds(): array
    {
        $out = [];
        foreach ($this->themeConfig->enabledThemeIds as $enabledId) {
            if (! is_string($enabledId)) {
                continue;
            }

            $normalized = strtolower(trim($enabledId));
            if ($normalized === '' || ! $this->isValidThemeIdentifier($normalized)) {
                continue;
            }

            if (! in_array($normalized, $out, true)) {
                $out[] = $normalized;
            }
        }

        sort($out);

        return $out;
    }

    private function isEnabled(string $themeId): bool
    {
        return in_array(strtolower(trim($themeId)), $this->normalizedEnabledThemeIds(), true);
    }

    private function manifestDirectoryMatchesId(string $directoryName): bool
    {
        $path = $this->manifestPathFor($directoryName);
        if (! is_file($path)) {
            return false;
        }

        try {
            /** @var mixed $manifest */
            $manifest = require $path;
        } catch (\Throwable) {
            return false;
        }

        if (! is_array($manifest) || ! isset($manifest['id']) || ! is_string($manifest['id'])) {
            return false;
        }

        return strtolower(trim($manifest['id'])) === strtolower(trim($directoryName));
    }

    private function assertPublicTemplateKey(string $templateKey): string
    {
        $key = trim($templateKey);
        if ($key === '' || ! $this->hasTemplate($key)) {
            throw new RuntimeException('Template is not available on the active theme.');
        }

        return $key;
    }

    private function manifestPathFor(string $themeId): string
    {
        return APPPATH . 'Views/themes/' . $themeId . '/ThemeManifest.php';
    }

    private function clearManifestCache(): void
    {
        $this->manifestCache = [];
    }

    private function actorId(?User $actor): ?int
    {
        if ($actor === null) {
            return null;
        }

        $id = $actor->id ?? null;

        return is_int($id) && $id > 0 ? $id : null;
    }

    /**
     * @return array{
     *     id: string,
     *     maximum_width: int,
     *     maximum_height: int,
     *     maximum_file_size: int,
     *     allowed_formats: list<string>,
     *     minimum_width: int|null,
     *     minimum_height: int|null
     * }
     */
    private function builtInCmsDefaultProfile(): array
    {
        return [
            'id'                => self::BASELINE_IMAGE_PROFILE_ID,
            'maximum_width'     => self::BASELINE_MAX_DIMENSION,
            'maximum_height'    => self::BASELINE_MAX_DIMENSION,
            'maximum_file_size' => self::BASELINE_MAX_FILE_SIZE,
            'allowed_formats'   => self::BASELINE_ALLOWED_FORMATS,
            'minimum_width'     => null,
            'minimum_height'    => null,
        ];
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array{
     *     id: string,
     *     maximum_width: int,
     *     maximum_height: int,
     *     maximum_file_size: int,
     *     allowed_formats: list<string>,
     *     minimum_width: int|null,
     *     minimum_height: int|null
     * }
     */
    private function normalizeImageProfile(string $id, array $raw): array
    {
        $fallback = $this->builtInCmsDefaultProfile();

        $maxWidth = $this->positiveIntOrNull($raw['maximum_width'] ?? null)
            ?? $fallback['maximum_width'];
        $maxHeight = $this->positiveIntOrNull($raw['maximum_height'] ?? null)
            ?? $fallback['maximum_height'];
        $maxBytes = $this->positiveIntOrNull($raw['maximum_file_size'] ?? null)
            ?? $fallback['maximum_file_size'];

        $formats = $fallback['allowed_formats'];
        if (isset($raw['allowed_formats']) && is_array($raw['allowed_formats']) && $raw['allowed_formats'] !== []) {
            $normalized = [];
            foreach ($raw['allowed_formats'] as $format) {
                if (! is_string($format)) {
                    continue;
                }
                $format = strtolower(trim($format));
                if ($format !== '') {
                    $normalized[] = $format;
                }
            }
            if ($normalized !== []) {
                $formats = array_values(array_unique($normalized));
            }
        }

        return [
            'id'                => $id,
            'maximum_width'     => $maxWidth,
            'maximum_height'    => $maxHeight,
            'maximum_file_size' => $maxBytes,
            'allowed_formats'   => $formats,
            'minimum_width'     => $this->positiveIntOrNull($raw['minimum_width'] ?? null),
            'minimum_height'    => $this->positiveIntOrNull($raw['minimum_height'] ?? null),
        ];
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        return null;
    }
}
