<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Dtos\CreateStaffUserDto;
use App\Dtos\UpdateStaffUserDto;
use App\Services\UserAdminService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/**
 * Control Panel staff user management (V2-003 / ADR-027 P0-1).
 *
 * Authorization: permission:user.manage (Admin-only matrix).
 */
class UserController extends BaseController
{
    public function index(): string
    {
        $service = $this->userAdminService();

        return view('admin/users/index', [
            'rows'           => $service->listForAdmin(),
            'invariant'      => $service->getAdminInvariantStatus(),
            'success'        => session()->getFlashdata('success'),
            'error'          => session()->getFlashdata('error'),
        ]);
    }

    public function create(): string
    {
        $service = $this->userAdminService();

        return view('admin/users/form', [
            'mode'              => 'create',
            'item'              => [
                'username'  => '',
                'email'     => '',
                'group'     => 'contributor',
                'is_active' => true,
            ],
            'assignableGroups'  => $service->getAssignableGroups(),
            'errors'            => [],
            'formAction'        => site_url('admin/users'),
            'showPassword'      => true,
            'canChangeGroup'    => true,
            'canChangeActive'   => true,
            'isAdmin'           => false,
        ]);
    }

    public function store(): ResponseInterface|RedirectResponse|string
    {
        $dto    = $this->createDtoFromRequest();
        $errors = $this->userAdminService()->create($dto, $this->actorId());
        if ($errors !== []) {
            return view('admin/users/form', [
                'mode'              => 'create',
                'item'              => $this->createFormDataFromDto($dto),
                'assignableGroups'  => $this->userAdminService()->getAssignableGroups(),
                'errors'            => $errors,
                'formAction'        => site_url('admin/users'),
                'showPassword'      => true,
                'canChangeGroup'    => true,
                'canChangeActive'   => true,
                'isAdmin'           => false,
            ]);
        }

        return redirect()->to('/admin/users')->with('success', 'User created.');
    }

    public function edit(int $id): ResponseInterface|RedirectResponse|string
    {
        $existing = $this->userAdminService()->findForEdit($id);
        if ($existing === null) {
            return redirect()->to('/admin/users')->with('error', 'User not found.');
        }

        return view('admin/users/form', [
            'mode'              => 'edit',
            'item'              => [
                'id'        => $existing['id'],
                'username'  => $existing['username'],
                'email'     => $existing['email'],
                'group'     => $existing['group'],
                'is_active' => $existing['is_active'],
            ],
            'assignableGroups'  => $this->userAdminService()->getAssignableGroups(),
            'errors'            => [],
            'formAction'        => site_url('admin/users/' . $existing['id']),
            'showPassword'      => false,
            'canChangeGroup'    => $existing['can_change_group'],
            'canChangeActive'   => $existing['can_change_active'],
            'isAdmin'           => $existing['is_admin'],
        ]);
    }

    public function update(int $id): ResponseInterface|RedirectResponse|string
    {
        $dto    = $this->updateDtoFromRequest();
        $errors = $this->userAdminService()->update($id, $dto, $this->actorId());
        if (isset($errors['_not_found'])) {
            return redirect()->to('/admin/users')->with('error', $errors['_not_found']);
        }

        if ($errors !== []) {
            $existing = $this->userAdminService()->findForEdit($id);

            return view('admin/users/form', [
                'mode'              => 'edit',
                'item'              => array_merge(
                    $this->updateFormDataFromDto($dto),
                    ['id' => $id, 'username' => $existing['username'] ?? ''],
                ),
                'assignableGroups'  => $this->userAdminService()->getAssignableGroups(),
                'errors'            => $errors,
                'formAction'        => site_url('admin/users/' . $id),
                'showPassword'      => false,
                'canChangeGroup'    => $existing['can_change_group'] ?? false,
                'canChangeActive'   => $existing['can_change_active'] ?? false,
                'isAdmin'           => $existing['is_admin'] ?? false,
            ]);
        }

        return redirect()->to('/admin/users')->with('success', 'User updated.');
    }

    public function activate(int $id): RedirectResponse
    {
        $errors = $this->userAdminService()->activate($id, $this->actorId());
        if (isset($errors['_not_found'])) {
            return redirect()->to('/admin/users')->with('error', $errors['_not_found']);
        }
        if (isset($errors['_invariant'])) {
            return redirect()->to('/admin/users')->with('error', $errors['_invariant']);
        }
        if (isset($errors['_persist'])) {
            return redirect()->to('/admin/users')->with('error', $errors['_persist']);
        }

        return redirect()->to('/admin/users')->with('success', 'User activated.');
    }

    public function deactivate(int $id): RedirectResponse
    {
        $errors = $this->userAdminService()->deactivate($id, $this->actorId());
        if (isset($errors['_not_found'])) {
            return redirect()->to('/admin/users')->with('error', $errors['_not_found']);
        }
        if (isset($errors['_invariant'])) {
            return redirect()->to('/admin/users')->with('error', $errors['_invariant']);
        }
        if (isset($errors['_persist'])) {
            return redirect()->to('/admin/users')->with('error', $errors['_persist']);
        }

        return redirect()->to('/admin/users')->with('success', 'User deactivated.');
    }

    private function userAdminService(): UserAdminService
    {
        return service('userAdminService');
    }

    private function actorId(): int
    {
        $user = auth()->user();

        return $user instanceof User ? (int) $user->id : 0;
    }

    private function createDtoFromRequest(): CreateStaffUserDto
    {
        $activeRaw = $this->request->getPost('is_active');

        return new CreateStaffUserDto(
            username: (string) ($this->request->getPost('username') ?? ''),
            email: (string) ($this->request->getPost('email') ?? ''),
            password: (string) ($this->request->getPost('password') ?? ''),
            passwordConfirm: (string) ($this->request->getPost('password_confirm') ?? ''),
            group: (string) ($this->request->getPost('group') ?? 'contributor'),
            isActive: $activeRaw === '1' || $activeRaw === 'on' || $activeRaw === true,
        );
    }

    private function updateDtoFromRequest(): UpdateStaffUserDto
    {
        $activeRaw = $this->request->getPost('is_active');

        return new UpdateStaffUserDto(
            email: (string) ($this->request->getPost('email') ?? ''),
            group: (string) ($this->request->getPost('group') ?? 'contributor'),
            isActive: $activeRaw === '1' || $activeRaw === 'on' || $activeRaw === true,
        );
    }

    /**
     * @return array{username: string, email: string, group: string, is_active: bool}
     */
    private function createFormDataFromDto(CreateStaffUserDto $dto): array
    {
        return [
            'username'  => strtolower(trim($dto->username)),
            'email'     => strtolower(trim($dto->email)),
            'group'     => strtolower(trim($dto->group)),
            'is_active' => $dto->isActive,
        ];
    }

    /**
     * @return array{email: string, group: string, is_active: bool}
     */
    private function updateFormDataFromDto(UpdateStaffUserDto $dto): array
    {
        return [
            'email'     => strtolower(trim($dto->email)),
            'group'     => strtolower(trim($dto->group)),
            'is_active' => $dto->isActive,
        ];
    }
}
