<?php

declare(strict_types=1);

namespace App\Controllers\Concerns;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Map ADR-019 OCC service `_conflict` results to HTTP 409 (Task 4.9C).
 */
trait OccConflictResponder
{
    /**
     * @param array<string, string> $errors
     */
    private function isOccConflict(array $errors): bool
    {
        return isset($errors['_conflict']);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed>  $viewData
     */
    private function respondOccConflict(array $errors, string $view, array $viewData): ResponseInterface
    {
        $message = (string) ($errors['_conflict'] ?? 'The content was modified by another session.');
        $version = (string) ($errors['lock_version'] ?? '');

        $viewData['errors'] = array_merge(
            is_array($viewData['errors'] ?? null) ? $viewData['errors'] : [],
            ['_conflict' => $message],
        );
        if ($version !== '') {
            $viewData['errors']['lock_version'] = $version;
            if (isset($viewData['item']) && is_array($viewData['item'])) {
                $viewData['item']['lock_version'] = (int) $version;
            }
        }
        $viewData['flashError'] = $message . ($version !== '' ? ' (current version: ' . $version . ')' : '');

        return $this->response
            ->setStatusCode(409)
            ->setBody(view($view, $viewData));
    }

    private function expectedLockVersionFromRequest(): ?int
    {
        $raw = $this->request->getPost('lock_version');
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_numeric($raw)) {
            return null;
        }
        $value = (int) $raw;

        return $value > 0 ? $value : null;
    }
}
