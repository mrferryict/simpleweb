<?php

declare(strict_types=1);

namespace App\Services;

use App\Dtos\MenuItemWriteDto;
use App\Entities\MenuItem;
use App\Enums\MenuLocation;
use App\Enums\MenuTargetType;
use App\Models\MenuModel;
use CodeIgniter\Validation\ValidationInterface;
use Uri\InvalidUriException;
use Uri\Rfc3986\Uri;

/**
 * Application boundary for Control Panel Menu items (Phase 2 / Tasks 2.2–2.4).
 *
 * Destination types: PAGE, POST_CATEGORY, EXTERNAL_URL (DOC-01 REQ-MENU-003).
 * Page/Category modules are not present yet — target_id is a deferred reference.
 * External URLs: HTTP/HTTPS only (DOC-07 §37); reject javascript/data/vbscript.
 */
class MenuService
{
    private const LABEL_MAX       = 150;
    private const DESTINATION_MAX = 500;

    public function __construct(
        private readonly MenuModel $menuModel,
        private readonly ValidationInterface $validation,
    ) {
    }

    /**
     * Deterministic flat list for a location: top-level by order, each
     * followed by its children by order (explicit ordering; Parent└──Child).
     *
     * @return list<MenuItem>
     */
    public function listByLocation(string $location): array
    {
        $normalized = $this->normalizeLocation($location);
        if ($normalized === null) {
            return [];
        }

        return $this->flattenHierarchy($this->buildTree($normalized));
    }

    /**
     * @return array{
     *     PRIMARY: list<array{item: MenuItem, children: list<MenuItem>}>,
     *     FOOTER: list<array{item: MenuItem, children: list<MenuItem>}>
     * }
     */
    public function listAllGrouped(): array
    {
        return [
            MenuLocation::Primary->value => $this->buildTree(MenuLocation::Primary->value),
            MenuLocation::Footer->value  => $this->buildTree(MenuLocation::Footer->value),
        ];
    }

    /**
     * Valid parent choices for the form (top-level only).
     *
     * @return list<MenuItem>
     */
    public function listValidParents(?int $excludeId = null): array
    {
        $parents = [];

        foreach (MenuLocation::values() as $location) {
            foreach ($this->menuModel->findTopLevelByLocationOrdered($location) as $item) {
                if ($excludeId !== null && $item->id === $excludeId) {
                    continue;
                }
                $parents[] = $item;
            }
        }

        return $parents;
    }

    public function findById(int $id): ?MenuItem
    {
        if ($id < 1) {
            return null;
        }

        /** @var MenuItem|null $item */
        $item = $this->menuModel->find($id);

        return $item instanceof MenuItem ? $item : null;
    }

    /**
     * @return array<string, string> Validation errors; empty on success.
     */
    #[\NoDiscard]
    public function create(MenuItemWriteDto $dto): array
    {
        $normalized = $this->normalize($dto);
        $errors     = $this->validate($normalized, null);
        if ($errors !== []) {
            return $errors;
        }

        $this->menuModel->insert($this->toRow($normalized));

        // Future: invalidate nav:menu:* cache keys (ADR-009).

        return [];
    }

    /**
     * @return array<string, string> Validation errors; empty on success.
     *                              Special key `_not_found` when the row is missing.
     */
    #[\NoDiscard]
    public function update(int $id, MenuItemWriteDto $dto): array
    {
        $existing = $this->findById($id);
        if ($existing === null) {
            return ['_not_found' => 'Menu item not found.'];
        }

        $normalized = $this->normalize($dto);
        $errors     = $this->validate($normalized, $id);
        if ($errors !== []) {
            return $errors;
        }

        $this->menuModel->update($id, $this->toRow($normalized));

        return [];
    }

    /**
     * Physically delete a leaf menu item.
     * Parents with children are rejected (deletion semantics not documented).
     *
     * @return array<string, string> Empty on success.
     */
    #[\NoDiscard]
    public function delete(int $id): array
    {
        if ($this->findById($id) === null) {
            return ['_not_found' => 'Menu item not found.'];
        }

        if ($this->menuModel->countChildren($id) > 0) {
            return [
                'parent_id' => 'Cannot delete a menu item that has child items. Remove or reassign children first.',
            ];
        }

        $this->menuModel->delete($id);

        return [];
    }

    /**
     * @return array{
     *     location: string,
     *     label: string,
     *     target_type: string,
     *     target_id: int|null,
     *     external_url: string,
     *     display_order: int,
     *     is_active: bool,
     *     parent_id: int|null
     * }
     */
    private function normalize(MenuItemWriteDto $dto): array
    {
        $targetType = strtoupper(trim($dto->targetType));

        return [
            'location'      => strtoupper(trim($dto->location)),
            'label'         => trim($dto->label),
            'target_type'   => $targetType,
            'target_id'     => $dto->targetId !== null && $dto->targetId > 0 ? $dto->targetId : null,
            'external_url'  => trim($dto->externalUrl),
            'display_order' => $dto->displayOrder,
            'is_active'     => $dto->isActive,
            'parent_id'     => $dto->parentId !== null && $dto->parentId > 0 ? $dto->parentId : null,
        ];
    }

    /**
     * @param array{
     *     location: string,
     *     label: string,
     *     target_type: string,
     *     target_id: int|null,
     *     external_url: string,
     *     display_order: int,
     *     is_active: bool,
     *     parent_id: int|null
     * } $data
     *
     * @return array<string, mixed>
     */
    private function toRow(array $data): array
    {
        $type = MenuTargetType::tryFromString($data['target_type']);

        return [
            'location'      => $data['location'],
            'parent_id'     => $data['parent_id'],
            'label'         => $data['label'],
            'target_type'   => $data['target_type'],
            'target_id'     => $type === MenuTargetType::ExternalUrl ? null : $data['target_id'],
            'destination'   => $type === MenuTargetType::ExternalUrl ? $data['external_url'] : '',
            'display_order' => $data['display_order'],
            'is_active'     => $data['is_active'] ? 1 : 0,
        ];
    }

    private function normalizeLocation(string $location): ?string
    {
        $case = MenuLocation::tryFromString($location);

        return $case?->value;
    }

    /**
     * @param array{
     *     location: string,
     *     label: string,
     *     target_type: string,
     *     target_id: int|null,
     *     external_url: string,
     *     display_order: int,
     *     is_active: bool,
     *     parent_id: int|null
     * } $data
     *
     * @return array<string, string>
     */
    private function validate(array $data, ?int $currentId): array
    {
        $rules = [
            'location' => [
                'label' => 'Location',
                'rules' => 'required|in_list[' . implode(',', MenuLocation::values()) . ']',
            ],
            'label' => [
                'label' => 'Label',
                'rules' => 'required|max_length[' . self::LABEL_MAX . ']',
            ],
            'target_type' => [
                'label' => 'Destination type',
                'rules' => 'required|in_list[' . implode(',', MenuTargetType::values()) . ']',
            ],
            'display_order' => [
                'label' => 'Display order',
                'rules' => 'required|integer|greater_than_equal_to[0]',
            ],
        ];

        $this->validation->reset();
        $this->validation->setRules($rules);

        $payload = [
            'location'      => $data['location'],
            'label'         => $data['label'],
            'target_type'   => $data['target_type'],
            'display_order' => (string) $data['display_order'],
        ];

        if (! $this->validation->run($payload)) {
            /** @var array<string, string> $errors */
            $errors = $this->validation->getErrors();

            return $errors;
        }

        if (MenuLocation::tryFromString($data['location']) === null) {
            return ['location' => 'The Location field must be PRIMARY or FOOTER.'];
        }

        $targetErrors = $this->validateDestination($data);
        if ($targetErrors !== []) {
            return $targetErrors;
        }

        return $this->validateHierarchy($data, $currentId);
    }

    /**
     * @param array{
     *     location: string,
     *     label: string,
     *     target_type: string,
     *     target_id: int|null,
     *     external_url: string,
     *     display_order: int,
     *     is_active: bool,
     *     parent_id: int|null
     * } $data
     *
     * @return array<string, string>
     */
    private function validateDestination(array $data): array
    {
        $type = MenuTargetType::tryFromString($data['target_type']);
        if ($type === null) {
            return ['target_type' => 'The Destination type field must be PAGE, POST_CATEGORY, or EXTERNAL_URL.'];
        }

        $hasTargetId    = $data['target_id'] !== null;
        $hasExternalUrl = $data['external_url'] !== '';

        return match ($type) {
            MenuTargetType::Page => $this->validatePageTarget($hasTargetId, $hasExternalUrl, $data['target_id']),
            MenuTargetType::PostCategory => $this->validatePostCategoryTarget($hasTargetId, $hasExternalUrl, $data['target_id']),
            MenuTargetType::ExternalUrl => $this->validateExternalUrlTarget($hasTargetId, $hasExternalUrl, $data['external_url']),
        };
    }

    /**
     * @return array<string, string>
     */
    private function validatePageTarget(bool $hasTargetId, bool $hasExternalUrl, ?int $targetId): array
    {
        if ($hasExternalUrl) {
            return ['destination' => 'Page destinations must not include an External URL.'];
        }

        if (! $hasTargetId || $targetId === null) {
            return ['target_id' => 'A Page destination requires a Page ID.'];
        }

        /** @var \App\Services\PageService $pageService */
        $pageService = service('pageService');
        if (! $pageService->existsForMenuTarget($targetId)) {
            return ['target_id' => 'The selected Page does not exist or is in Trash.'];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function validatePostCategoryTarget(bool $hasTargetId, bool $hasExternalUrl, ?int $targetId): array
    {
        if ($hasExternalUrl) {
            return ['destination' => 'Post Category destinations must not include an External URL.'];
        }

        if (! $hasTargetId || $targetId === null) {
            return ['target_id' => 'A Post Category destination requires a Category ID.'];
        }

        /** @var CategoryService $categoryService */
        $categoryService = service('categoryService');
        if (! $categoryService->existsForMenuTarget($targetId)) {
            return ['target_id' => 'The selected Category does not exist or is inactive.'];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function validateExternalUrlTarget(bool $hasTargetId, bool $hasExternalUrl, string $url): array
    {
        if ($hasTargetId) {
            return ['target_id' => 'External URL destinations must not include a Page or Category ID.'];
        }

        if (! $hasExternalUrl) {
            return ['destination' => 'An External URL destination is required.'];
        }

        if (strlen($url) > self::DESTINATION_MAX) {
            return ['destination' => 'The External URL field cannot exceed ' . self::DESTINATION_MAX . ' characters.'];
        }

        $lower = strtolower($url);
        foreach (['javascript:', 'data:', 'vbscript:'] as $dangerous) {
            if (str_starts_with($lower, $dangerous)) {
                return ['destination' => 'The External URL scheme is not allowed.'];
            }
        }

        try {
            $uri = new Uri($url);
        } catch (InvalidUriException) {
            return ['destination' => 'The External URL is not a valid URL.'];
        }

        $scheme = strtolower((string) $uri->getScheme());
        if ($scheme !== 'http' && $scheme !== 'https') {
            return ['destination' => 'The External URL must use the http or https scheme.'];
        }

        if ($uri->getHost() === null || $uri->getHost() === '') {
            return ['destination' => 'The External URL must include a host.'];
        }

        return [];
    }

    /**
     * @param array{
     *     location: string,
     *     label: string,
     *     target_type: string,
     *     target_id: int|null,
     *     external_url: string,
     *     display_order: int,
     *     is_active: bool,
     *     parent_id: int|null
     * } $data
     *
     * @return array<string, string>
     */
    private function validateHierarchy(array $data, ?int $currentId): array
    {
        $parentId = $data['parent_id'];

        if ($currentId !== null && $this->menuModel->countChildren($currentId) > 0) {
            if ($parentId !== null) {
                return [
                    'parent_id' => 'A menu item that has children cannot become a child (maximum two levels).',
                ];
            }

            $existing = $this->findById($currentId);
            if ($existing !== null && $existing->location !== $data['location']) {
                return [
                    'location' => 'Cannot change location of a menu item that has children.',
                ];
            }
        }

        if ($parentId === null) {
            return [];
        }

        if ($currentId !== null && $parentId === $currentId) {
            return ['parent_id' => 'A menu item cannot be its own parent.'];
        }

        $parent = $this->findById($parentId);
        if ($parent === null) {
            return ['parent_id' => 'The selected parent menu item does not exist.'];
        }

        if ($parent->parent_id !== null) {
            return [
                'parent_id' => 'The selected parent is already a child. Maximum hierarchy is two levels.',
            ];
        }

        if ($parent->location !== $data['location']) {
            return [
                'parent_id' => 'Parent and child must belong to the same menu location.',
            ];
        }

        return [];
    }

    /**
     * @return list<array{item: MenuItem, children: list<MenuItem>}>
     */
    private function buildTree(string $location): array
    {
        $all      = $this->menuModel->findByLocationOrdered($location);
        $parents  = [];
        $children = [];

        foreach ($all as $item) {
            if ($item->parent_id === null) {
                $parents[] = $item;
            } else {
                $children[$item->parent_id] ??= [];
                $children[$item->parent_id][] = $item;
            }
        }

        usort(
            $parents,
            static fn (MenuItem $a, MenuItem $b): int => [$a->display_order, $a->id] <=> [$b->display_order, $b->id],
        );

        $tree = [];
        foreach ($parents as $parent) {
            $kids = $children[$parent->id] ?? [];
            usort(
                $kids,
                static fn (MenuItem $a, MenuItem $b): int => [$a->display_order, $a->id] <=> [$b->display_order, $b->id],
            );
            $tree[] = [
                'item'     => $parent,
                'children' => $kids,
            ];
            unset($children[$parent->id]);
        }

        foreach ($children as $orphans) {
            foreach ($orphans as $orphan) {
                $tree[] = [
                    'item'     => $orphan,
                    'children' => [],
                ];
            }
        }

        return $tree;
    }

    /**
     * @param list<array{item: MenuItem, children: list<MenuItem>}> $tree
     *
     * @return list<MenuItem>
     */
    private function flattenHierarchy(array $tree): array
    {
        $flat = [];
        foreach ($tree as $node) {
            $flat[] = $node['item'];
            foreach ($node['children'] as $child) {
                $flat[] = $child;
            }
        }

        return $flat;
    }
}
