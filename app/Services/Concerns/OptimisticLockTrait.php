<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Enums\RevisionResourceType;
use App\Services\Revision\RevisionService;

/**
 * Shared OCC helpers for Page/Post mutations (ADR-019).
 *
 * @property RevisionService $revisionService
 * @property \CodeIgniter\Database\BaseConnection $db
 */
trait OptimisticLockTrait
{
    /**
     * @return array{ok: true, expected: int}|array{ok: false, errors: array<string, string>}
     */
    private function beginOccMutation(string $table, int $id, ?int $expectedLockVersion): array
    {
        $locked = $this->revisionService->lockParentRow($table, $id);
        if ($locked === null) {
            return ['ok' => false, 'errors' => ['_not_found' => 'Record not found.']];
        }

        $expected = $expectedLockVersion ?? (int) $locked['lock_version'];
        if (! $this->revisionService->bumpLockVersion($table, $id, $expected)) {
            $current = $this->revisionService->currentLockVersion($table, $id) ?? $expected;

            return [
                'ok'     => false,
                'errors' => [
                    '_conflict'    => 'The content was modified by another session.',
                    'lock_version' => (string) $current,
                ],
            ];
        }

        return ['ok' => true, 'expected' => $expected];
    }

    private function actorId(?\CodeIgniter\Shield\Entities\User $actor): ?int
    {
        if ($actor === null || $actor->id === null) {
            return null;
        }

        return (int) $actor->id;
    }
}
