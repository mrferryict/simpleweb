<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Audit\AuditService;

/**
 * Read-only Audit Trail Control Panel (Phase 4 / Task 4.9E / ADR-019 AUTHZ-007).
 *
 * Authorization: permission:audit.view (Admin-only matrix).
 * Viewing this page must not append audit rows.
 */
class AuditController extends BaseController
{
    /**
     * GET /admin/audit
     */
    public function index(): string
    {
        return view('admin/audit/index', [
            'rows' => $this->auditService()->listRecentForAdmin(),
        ]);
    }

    private function auditService(): AuditService
    {
        return service('auditService');
    }
}
