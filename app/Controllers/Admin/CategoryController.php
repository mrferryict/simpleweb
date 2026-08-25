<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Dtos\CategoryWriteDto;
use App\Services\CategoryService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Control Panel Category foundation (Phase 3 / Task 3.7 / REQ-CAT-002).
 */
class CategoryController extends BaseController
{
    public function index(): string
    {
        return view('admin/categories/index', [
            'rows'    => $this->categoryService()->listAll(),
            'success' => session()->getFlashdata('success'),
            'error'   => session()->getFlashdata('error'),
        ]);
    }

    public function create(): string
    {
        return view('admin/categories/form', [
            'mode'       => 'create',
            'item'       => ['name' => '', 'slug' => '', 'is_active' => true],
            'errors'     => [],
            'formAction' => site_url('admin/categories'),
        ]);
    }

    public function store(): ResponseInterface|RedirectResponse|string
    {
        $dto    = $this->dtoFromRequest();
        $errors = $this->categoryService()->create($dto);
        if ($errors !== []) {
            return view('admin/categories/form', [
                'mode'       => 'create',
                'item'       => $this->formDataFromDto($dto),
                'errors'     => $errors,
                'formAction' => site_url('admin/categories'),
            ]);
        }

        return redirect()->to('/admin/categories')->with('success', 'Category created.');
    }

    public function edit(int $id): ResponseInterface|RedirectResponse|string
    {
        $category = $this->categoryService()->findById($id);
        if ($category === null) {
            return redirect()->to('/admin/categories')->with('error', 'Category not found.');
        }

        return view('admin/categories/form', [
            'mode'       => 'edit',
            'item'       => [
                'id'        => $category->id,
                'name'      => $category->name,
                'slug'      => $category->slug,
                'is_active' => $category->is_active,
            ],
            'errors'     => [],
            'formAction' => site_url('admin/categories/' . $category->id),
        ]);
    }

    public function update(int $id): ResponseInterface|RedirectResponse|string
    {
        $dto    = $this->dtoFromRequest();
        $errors = $this->categoryService()->update($id, $dto);
        if (isset($errors['_not_found'])) {
            return redirect()->to('/admin/categories')->with('error', $errors['_not_found']);
        }

        if ($errors !== []) {
            return view('admin/categories/form', [
                'mode'       => 'edit',
                'item'       => array_merge($this->formDataFromDto($dto), ['id' => $id]),
                'errors'     => $errors,
                'formAction' => site_url('admin/categories/' . $id),
            ]);
        }

        return redirect()->to('/admin/categories')->with('success', 'Category updated.');
    }

    public function deactivate(int $id): RedirectResponse
    {
        $errors = $this->categoryService()->deactivate($id);
        if (isset($errors['_not_found'])) {
            return redirect()->to('/admin/categories')->with('error', $errors['_not_found']);
        }

        return redirect()->to('/admin/categories')->with('success', 'Category deactivated.');
    }

    public function restore(int $id): RedirectResponse
    {
        $errors = $this->categoryService()->restore($id);
        if (isset($errors['_not_found'])) {
            return redirect()->to('/admin/categories')->with('error', $errors['_not_found']);
        }

        return redirect()->to('/admin/categories')->with('success', 'Category restored.');
    }

    private function categoryService(): CategoryService
    {
        return service('categoryService');
    }

    private function dtoFromRequest(): CategoryWriteDto
    {
        $activeRaw = $this->request->getPost('is_active');

        return new CategoryWriteDto(
            name: (string) ($this->request->getPost('name') ?? ''),
            slug: (string) ($this->request->getPost('slug') ?? ''),
            isActive: $activeRaw === '1' || $activeRaw === 'on' || $activeRaw === true,
        );
    }

    /**
     * @return array{name: string, slug: string, is_active: bool}
     */
    private function formDataFromDto(CategoryWriteDto $dto): array
    {
        return [
            'name'      => $dto->name,
            'slug'      => $dto->slug,
            'is_active' => $dto->isActive,
        ];
    }
}
