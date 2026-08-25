<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Dtos\MenuItemWriteDto;
use App\Enums\MenuLocation;
use App\Enums\MenuTargetType;
use App\Services\MenuService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Control Panel Menu CRUD (Phase 2 / Tasks 2.2–2.4).
 *
 * Authorization: SessionAuth + group + permission:menu.manage (DOC-03 / REQ-MENU-006).
 */
class MenuController extends BaseController
{
    /**
     * GET /admin/menus
     */
    public function index(): string
    {
        $grouped = $this->menuService()->listAllGrouped();

        return view('admin/menus/index', [
            'grouped' => $grouped,
            'success' => session()->getFlashdata('success'),
            'error'   => session()->getFlashdata('error'),
        ]);
    }

    /**
     * GET /admin/menus/new
     */
    public function create(): string
    {
        return view('admin/menus/form', [
            'mode'        => 'create',
            'item'        => $this->emptyFormData(),
            'locations'   => MenuLocation::values(),
            'targetTypes' => MenuTargetType::cases(),
            'parents'     => $this->menuService()->listValidParents(),
            'errors'      => [],
            'formAction'  => site_url('admin/menus'),
        ]);
    }

    /**
     * POST /admin/menus
     */
    public function store(): ResponseInterface|RedirectResponse|string
    {
        $dto    = $this->dtoFromRequest();
        $errors = $this->menuService()->create($dto);
        if ($errors !== []) {
            return view('admin/menus/form', [
                'mode'        => 'create',
                'item'        => $this->formDataFromDto($dto),
                'locations'   => MenuLocation::values(),
                'targetTypes' => MenuTargetType::cases(),
                'parents'     => $this->menuService()->listValidParents(),
                'errors'      => $errors,
                'formAction'  => site_url('admin/menus'),
            ]);
        }

        return redirect()->to('/admin/menus')->with('success', 'Menu item created.');
    }

    /**
     * GET /admin/menus/{id}/edit
     */
    public function edit(int $id): ResponseInterface|RedirectResponse|string
    {
        $item = $this->menuService()->findById($id);
        if ($item === null) {
            return redirect()->to('/admin/menus')->with('error', 'Menu item not found.');
        }

        return view('admin/menus/form', [
            'mode'        => 'edit',
            'item'        => [
                'id'            => $item->id,
                'location'      => $item->location,
                'parent_id'     => $item->parent_id,
                'label'         => $item->label,
                'target_type'   => $item->target_type,
                'target_id'     => $item->target_id,
                'external_url'  => $item->destination,
                'display_order' => $item->display_order,
                'is_active'     => $item->is_active,
            ],
            'locations'   => MenuLocation::values(),
            'targetTypes' => MenuTargetType::cases(),
            'parents'     => $this->menuService()->listValidParents($item->id),
            'errors'      => [],
            'formAction'  => site_url('admin/menus/' . $item->id),
        ]);
    }

    /**
     * POST /admin/menus/{id}
     */
    public function update(int $id): ResponseInterface|RedirectResponse|string
    {
        $dto    = $this->dtoFromRequest();
        $errors = $this->menuService()->update($id, $dto);
        if (isset($errors['_not_found'])) {
            return redirect()->to('/admin/menus')->with('error', $errors['_not_found']);
        }

        if ($errors !== []) {
            return view('admin/menus/form', [
                'mode'        => 'edit',
                'item'        => array_merge($this->formDataFromDto($dto), ['id' => $id]),
                'locations'   => MenuLocation::values(),
                'targetTypes' => MenuTargetType::cases(),
                'parents'     => $this->menuService()->listValidParents($id),
                'errors'      => $errors,
                'formAction'  => site_url('admin/menus/' . $id),
            ]);
        }

        return redirect()->to('/admin/menus')->with('success', 'Menu item updated.');
    }

    /**
     * POST /admin/menus/{id}/delete
     */
    public function delete(int $id): RedirectResponse
    {
        $errors = $this->menuService()->delete($id);
        if (isset($errors['_not_found'])) {
            return redirect()->to('/admin/menus')->with('error', $errors['_not_found']);
        }

        if ($errors !== []) {
            return redirect()->to('/admin/menus')->with('error', implode(' ', $errors));
        }

        return redirect()->to('/admin/menus')->with('success', 'Menu item deleted.');
    }

    private function menuService(): MenuService
    {
        return service('menuService');
    }

    private function dtoFromRequest(): MenuItemWriteDto
    {
        $activeRaw = $this->request->getPost('is_active');
        $parentRaw = $this->request->getPost('parent_id');
        $parentId  = null;
        if ($parentRaw !== null && $parentRaw !== '' && is_numeric($parentRaw)) {
            $parentId = (int) $parentRaw;
            if ($parentId < 1) {
                $parentId = null;
            }
        }

        $targetIdRaw = $this->request->getPost('target_id');
        $targetId    = null;
        if ($targetIdRaw !== null && $targetIdRaw !== '' && is_numeric($targetIdRaw)) {
            $targetId = (int) $targetIdRaw;
            if ($targetId < 1) {
                $targetId = null;
            }
        }

        return new MenuItemWriteDto(
            location: (string) ($this->request->getPost('location') ?? ''),
            label: (string) ($this->request->getPost('label') ?? ''),
            targetType: (string) ($this->request->getPost('target_type') ?? ''),
            targetId: $targetId,
            externalUrl: (string) ($this->request->getPost('external_url') ?? ''),
            displayOrder: (int) ($this->request->getPost('display_order') ?? -1),
            isActive: $activeRaw === '1' || $activeRaw === 'on' || $activeRaw === true || $activeRaw === 1,
            parentId: $parentId,
        );
    }

    /**
     * @return array{
     *     location: string,
     *     parent_id: int|null,
     *     label: string,
     *     target_type: string,
     *     target_id: int|null,
     *     external_url: string,
     *     display_order: int,
     *     is_active: bool
     * }
     */
    private function emptyFormData(): array
    {
        return [
            'location'      => MenuLocation::Primary->value,
            'parent_id'     => null,
            'label'         => '',
            'target_type'   => MenuTargetType::ExternalUrl->value,
            'target_id'     => null,
            'external_url'  => '',
            'display_order' => 0,
            'is_active'     => true,
        ];
    }

    /**
     * @return array{
     *     location: string,
     *     parent_id: int|null,
     *     label: string,
     *     target_type: string,
     *     target_id: int|null,
     *     external_url: string,
     *     display_order: int,
     *     is_active: bool
     * }
     */
    private function formDataFromDto(MenuItemWriteDto $dto): array
    {
        return [
            'location'      => $dto->location,
            'parent_id'     => $dto->parentId,
            'label'         => $dto->label,
            'target_type'   => $dto->targetType,
            'target_id'     => $dto->targetId,
            'external_url'  => $dto->externalUrl,
            'display_order' => $dto->displayOrder,
            'is_active'     => $dto->isActive,
        ];
    }
}
