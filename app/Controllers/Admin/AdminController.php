<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * Minimal authenticated Control Panel shell (DOC-08 § /admin/*).
 *
 * Authentication is enforced by Shield's session filter on the route group.
 */
class AdminController extends BaseController
{
    /**
     * GET /admin — Control Panel landing placeholder.
     */
    public function index(): string
    {
        $username = auth()->user()?->username;

        return view('admin/dashboard/index', [
            'username'  => is_string($username) ? $username : null,
            'activeNav' => 'dashboard',
        ]);
    }
}
