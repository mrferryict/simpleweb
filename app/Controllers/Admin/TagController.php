<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Dtos\TagWriteDto;
use App\Services\TagService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Control Panel Tag foundation (Phase 3 / Task 3.7 / REQ-TAG-002).
 */
class TagController extends BaseController
{
    public function index(): string
    {
        return view('admin/tags/index', [
            'rows'    => $this->tagService()->listAll(),
            'success' => session()->getFlashdata('success'),
            'error'   => session()->getFlashdata('error'),
        ]);
    }

    public function create(): string
    {
        return view('admin/tags/form', [
            'mode'       => 'create',
            'item'       => ['name' => '', 'slug' => ''],
            'errors'     => [],
            'formAction' => site_url('admin/tags'),
        ]);
    }

    public function store(): ResponseInterface|RedirectResponse|string
    {
        $dto    = $this->dtoFromRequest();
        $errors = $this->tagService()->create($dto);
        if ($errors !== []) {
            return view('admin/tags/form', [
                'mode'       => 'create',
                'item'       => $this->formDataFromDto($dto),
                'errors'     => $errors,
                'formAction' => site_url('admin/tags'),
            ]);
        }

        return redirect()->to('/admin/tags')->with('success', 'Tag created.');
    }

    public function edit(int $id): ResponseInterface|RedirectResponse|string
    {
        $tag = $this->tagService()->findById($id);
        if ($tag === null) {
            return redirect()->to('/admin/tags')->with('error', 'Tag not found.');
        }

        return view('admin/tags/form', [
            'mode'       => 'edit',
            'item'       => [
                'id'   => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ],
            'errors'     => [],
            'formAction' => site_url('admin/tags/' . $tag->id),
        ]);
    }

    public function update(int $id): ResponseInterface|RedirectResponse|string
    {
        $dto    = $this->dtoFromRequest();
        $errors = $this->tagService()->update($id, $dto);
        if (isset($errors['_not_found'])) {
            return redirect()->to('/admin/tags')->with('error', $errors['_not_found']);
        }

        if ($errors !== []) {
            return view('admin/tags/form', [
                'mode'       => 'edit',
                'item'       => array_merge($this->formDataFromDto($dto), ['id' => $id]),
                'errors'     => $errors,
                'formAction' => site_url('admin/tags/' . $id),
            ]);
        }

        return redirect()->to('/admin/tags')->with('success', 'Tag updated.');
    }

    private function tagService(): TagService
    {
        return service('tagService');
    }

    private function dtoFromRequest(): TagWriteDto
    {
        return new TagWriteDto(
            name: (string) ($this->request->getPost('name') ?? ''),
            slug: (string) ($this->request->getPost('slug') ?? ''),
        );
    }

    /**
     * @return array{name: string, slug: string}
     */
    private function formDataFromDto(TagWriteDto $dto): array
    {
        return [
            'name' => $dto->name,
            'slug' => $dto->slug,
        ];
    }
}
