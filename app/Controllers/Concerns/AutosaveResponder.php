<?php

declare(strict_types=1);

namespace App\Controllers\Concerns;

use App\Entities\Revision;
use App\Enums\RevisionResourceType;
use App\Services\Revision\RevisionService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * HTMX autosave response mapping (ADR-019 / Task 4.9D).
 */
trait AutosaveResponder
{
    /**
     * @param array<string, string> $errors
     */
    private function respondAutosaveResult(
        array $errors,
        RevisionResourceType $type,
        int $resourceId,
        int $currentLockVersion,
    ): ResponseInterface {
        if (isset($errors['_not_found'])) {
            return $this->response
                ->setStatusCode(404)
                ->setBody(view('admin/_partials/autosave_status', [
                    'state'   => 'not_found',
                    'message' => $errors['_not_found'],
                    'errors'  => [],
                ]));
        }

        if (isset($errors['_forbidden'])) {
            return $this->response
                ->setStatusCode(403)
                ->setBody(view('admin/_partials/autosave_status', [
                    'state'   => 'forbidden',
                    'message' => $errors['_forbidden'],
                    'errors'  => [],
                ]));
        }

        if ($this->isOccConflict($errors)) {
            $version = isset($errors['lock_version']) && is_numeric($errors['lock_version'])
                ? (int) $errors['lock_version']
                : $currentLockVersion;

            return $this->response
                ->setStatusCode(409)
                ->setBody(view('admin/_partials/autosave_status', [
                    'state'       => 'conflict',
                    'message'     => (string) ($errors['_conflict'] ?? 'The content was modified by another session.'),
                    'lockVersion' => $version,
                    'errors'      => [],
                ]));
        }

        if ($errors !== []) {
            return $this->response
                ->setStatusCode(422)
                ->setBody(view('admin/_partials/autosave_status', [
                    'state'   => 'error',
                    'message' => 'Autosave could not be saved.',
                    'errors'  => $errors,
                ]));
        }

        /** @var RevisionService $revisions */
        $revisions = service('revisionService');
        $latest    = $revisions->findLatestAutosave($type, $resourceId);

        $savedAt = null;
        $revNum  = null;
        if ($latest instanceof Revision) {
            $revNum  = (int) $latest->revision_number;
            $created = $latest->created_at;
            $savedAt = is_object($created) && method_exists($created, 'format')
                ? $created->format('Y-m-d H:i:s')
                : (string) $created;
        }

        return $this->response
            ->setStatusCode(200)
            ->setBody(view('admin/_partials/autosave_status', [
                'state'          => 'success',
                'message'        => null,
                'revisionNumber' => $revNum,
                'lockVersion'    => $currentLockVersion,
                'savedAt'        => $savedAt,
                'errors'         => [],
            ]));
    }
}
