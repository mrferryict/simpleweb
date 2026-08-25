<?php

declare(strict_types=1);

namespace App\Services;

use App\Dtos\CreateScheduledActionDto;
use App\Dtos\ScheduledContentRunResult;
use App\Entities\ScheduledAction;
use App\Enums\ScheduledActionResultCode;
use App\Enums\ScheduledActionStatus;
use App\Enums\ScheduledActionTargetType;
use App\Enums\ScheduledActionType;
use App\Models\ScheduledActionModel;
use App\Services\Cache\PublicContentCacheInvalidator;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Shield\Entities\User;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Scheduled PUBLISH/UNPUBLISH (ADR-021). Create/cancel authorized here; Spark executes.
 */
class ScheduledContentService
{
    private const BATCH_SIZE     = 50;
    private const LEASE_MINUTES  = 5;
    private const SKIP_CODES     = [
        'TARGET_TRASH',
        'TARGET_ARCHIVED',
        'TARGET_PENDING_REVIEW',
        'TARGET_ALREADY_PUBLISHED',
        'TARGET_ALREADY_UNPUBLISHED',
        'TARGET_MISSING',
        'INVALID_SOURCE_STATE',
        'LOCK_VERSION_CONFLICT',
    ];

    public function __construct(
        private readonly ScheduledActionModel $scheduledActionModel,
        private readonly PageService $pageService,
        private readonly PostService $postService,
        private readonly SettingService $settingService,
        private readonly PublicContentCacheInvalidator $publicContentCache,
        private readonly BaseConnection $db,
    ) {
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function create(CreateScheduledActionDto $dto, User $actor): array
    {
        $targetType = ScheduledActionTargetType::tryFrom(strtolower(trim($dto->targetType)));
        $actionType = ScheduledActionType::tryFrom(strtoupper(trim($dto->actionType)));
        if ($targetType === null) {
            return ['target_type' => 'Target type is not supported.'];
        }
        if ($actionType === null) {
            return ['action_type' => 'Action type is not supported.'];
        }
        if ($dto->targetId < 1) {
            return ['target_id' => 'Target was not found.'];
        }

        $authError = $this->authorizeCreate($targetType, $dto->targetId, $actionType, $actor);
        if ($authError !== null) {
            return $authError;
        }

        $existsError = $this->assertTargetExists($targetType, $dto->targetId);
        if ($existsError !== null) {
            return $existsError;
        }

        $converted = $this->localToUtc($dto->executeAtLocal);
        if ($converted['error'] !== null) {
            return ['execute_at' => $converted['error']];
        }

        $utcNow = $this->utcNow();
        if ($converted['utc'] < $utcNow) {
            return ['execute_at' => 'Schedule time must not be in the past.'];
        }

        $createdBy = $actor->id !== null ? (int) $actor->id : null;

        try {
            $inserted = $this->scheduledActionModel->insert([
                'target_type'    => $targetType->value,
                'target_id'      => $dto->targetId,
                'action_type'    => $actionType->value,
                'execute_at'     => $converted['utc'],
                'status'         => ScheduledActionStatus::Pending->value,
                'attempts'       => 0,
                'created_by'     => $createdBy,
            ], true);
        } catch (DatabaseException $e) {
            if ($this->isUniquePendingViolation($e->getMessage())) {
                return ['execute_at' => 'A pending schedule already exists for this action and time.'];
            }

            log_message('error', 'Scheduled action insert failed: {id}', ['id' => $dto->targetId]);

            return ['_persist' => 'Unable to create schedule.'];
        }

        if ($inserted === false) {
            $dbError = $this->db->error();
            $message = is_array($dbError) ? (string) ($dbError['message'] ?? '') : '';
            if ($this->isUniquePendingViolation($message)) {
                return ['execute_at' => 'A pending schedule already exists for this action and time.'];
            }

            return ['_persist' => 'Unable to create schedule.'];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function cancel(int $scheduleId, User $actor, ?string $expectedTargetType = null, ?int $expectedTargetId = null): array
    {
        $action = $this->scheduledActionModel->find($scheduleId);
        if (! $action instanceof ScheduledAction) {
            return ['_not_found' => 'Schedule not found.'];
        }

        if ($expectedTargetType !== null && $action->target_type !== $expectedTargetType) {
            return ['_not_found' => 'Schedule not found.'];
        }
        if ($expectedTargetId !== null && (int) $action->target_id !== $expectedTargetId) {
            return ['_not_found' => 'Schedule not found.'];
        }

        $targetType = ScheduledActionTargetType::tryFrom((string) $action->target_type);
        $actionType = ScheduledActionType::tryFrom((string) $action->action_type);
        if ($targetType === null || $actionType === null) {
            return ['_not_found' => 'Schedule not found.'];
        }

        $authError = $this->authorizeCreate($targetType, (int) $action->target_id, $actionType, $actor);
        if ($authError !== null) {
            return $authError;
        }

        if ($action->status !== ScheduledActionStatus::Pending->value) {
            return ['_status' => 'Only pending schedules can be cancelled.'];
        }

        $now = $this->utcNow();
        $this->scheduledActionModel->update($scheduleId, [
            'status'         => ScheduledActionStatus::Cancelled->value,
            'result_code'    => ScheduledActionResultCode::Cancelled->value,
            'result_message' => 'Cancelled before execution.',
            'processed_at'   => $now,
        ]);

        return [];
    }

    /**
     * @return list<ScheduledAction>
     */
    public function listForTarget(string $targetType, int $targetId): array
    {
        /** @var list<ScheduledAction> $rows */
        $rows = $this->scheduledActionModel
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->orderBy('execute_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll(50);

        return $rows;
    }

    public function siteTimezone(): string
    {
        return $this->settingService->siteTimezone();
    }

    public function formatExecuteAtLocal(string $utcDateTime): string
    {
        try {
            $utc = new DateTimeImmutable($utcDateTime, new DateTimeZone('UTC'));
        } catch (Throwable) {
            return $utcDateTime;
        }

        return $utc->setTimezone(new DateTimeZone($this->siteTimezone()))->format('Y-m-d H:i:s');
    }

    public function formatExecuteAtLocalInput(string $utcDateTime): string
    {
        try {
            $utc = new DateTimeImmutable($utcDateTime, new DateTimeZone('UTC'));
        } catch (Throwable) {
            return '';
        }

        return $utc->setTimezone(new DateTimeZone($this->siteTimezone()))->format('Y-m-d\TH:i');
    }

    /**
     * Claim due rows and execute them (ADR-021 §12).
     *
     * @param int|null $expectedLockVersion Test-only OCC override; production Spark passes null.
     */
    #[\NoDiscard]
    public function processDue(?int $expectedLockVersion = null): ScheduledContentRunResult
    {
        $ids     = $this->claimDue();
        $applied = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($ids as $id) {
            $outcome = $this->executeClaimed($id, $expectedLockVersion);
            match ($outcome) {
                'applied' => $applied++,
                'skipped' => $skipped++,
                default   => $failed++,
            };
        }

        return new ScheduledContentRunResult(
            claimed: count($ids),
            applied: $applied,
            skipped: $skipped,
            failed: $failed,
        );
    }

    /**
     * @return list<int>
     */
    public function claimDue(): array
    {
        $now   = $this->utcNow();
        $lease = $this->utcPlusMinutes(self::LEASE_MINUTES);
        $table = $this->db->prefixTable('scheduled_actions');

        $this->db->transStart();

        $forUpdate = $this->db->DBDriver === 'SQLite3' ? '' : ' FOR UPDATE';
        $sql       = 'SELECT id FROM `' . $table . '` WHERE '
            . "((status = 'PENDING' AND execute_at <= ?) OR (status = 'PROCESSING' AND lease_until <= ?)) "
            . 'ORDER BY execute_at ASC, id ASC LIMIT ' . self::BATCH_SIZE . $forUpdate;

        $result = $this->db->query($sql, [$now, $now]);
        $ids    = [];
        if ($result !== false) {
            foreach ($result->getResultArray() as $row) {
                $ids[] = (int) $row['id'];
            }
        }

        foreach ($ids as $id) {
            $this->db->table('scheduled_actions')
                ->set('status', ScheduledActionStatus::Processing->value)
                ->set('claimed_at', $now)
                ->set('lease_until', $lease)
                ->set('attempts', 'attempts + 1', false)
                ->set('updated_at', $now)
                ->where('id', $id)
                ->update();
        }

        $this->db->transComplete();

        return $ids;
    }

    /**
     * @return 'applied'|'skipped'|'failed'
     */
    public function executeClaimed(int $scheduleId, ?int $expectedLockVersion = null): string
    {
        $action = $this->scheduledActionModel->find($scheduleId);
        if (! $action instanceof ScheduledAction) {
            return 'failed';
        }

        if ($action->status !== ScheduledActionStatus::Processing->value) {
            return 'skipped';
        }

        try {
            $this->db->transStart();

            $mutation = $this->mutateContent($action, $expectedLockVersion);
            $this->persistExecutionResult($scheduleId, $mutation);

            $this->db->transComplete();
            if (! $this->db->transStatus()) {
                $this->markFailed($scheduleId, 'Unable to persist scheduled execution.');

                return 'failed';
            }

            if ($mutation['kind'] === 'applied') {
                $this->invalidatePublicCache((string) $action->target_type, (int) $action->target_id);
            }

            return $mutation['kind'];
        } catch (Throwable $e) {
            if ($this->db->transDepth() > 0) {
                $this->db->transRollback();
            }

            $this->markFailed($scheduleId, 'Execution error.');
            log_message('error', 'Scheduled content execution failed for action {id}', ['id' => $scheduleId]);

            return 'failed';
        }
    }

    /**
     * @return array{kind: 'applied'|'skipped'|'failed', code: string, message: string}
     */
    private function mutateContent(ScheduledAction $action, ?int $expectedLockVersion): array
    {
        $actionType = ScheduledActionType::tryFrom((string) $action->action_type);
        $targetType = ScheduledActionTargetType::tryFrom((string) $action->target_type);
        if ($actionType === null || $targetType === null) {
            return [
                'kind'    => 'skipped',
                'code'    => ScheduledActionResultCode::InvalidSourceState->value,
                'message' => 'Unsupported scheduled action.',
            ];
        }

        $errors = $actionType === ScheduledActionType::Publish
            ? ($targetType === ScheduledActionTargetType::Page
                ? $this->pageService->applyScheduledPublish((int) $action->target_id, $expectedLockVersion)
                : $this->postService->applyScheduledPublish((int) $action->target_id, $expectedLockVersion))
            : ($targetType === ScheduledActionTargetType::Page
                ? $this->pageService->applyScheduledUnpublish((int) $action->target_id, $expectedLockVersion)
                : $this->postService->applyScheduledUnpublish((int) $action->target_id, $expectedLockVersion));

        if ($errors === []) {
            return [
                'kind'    => 'applied',
                'code'    => ScheduledActionResultCode::Applied->value,
                'message' => 'Applied.',
            ];
        }

        $code = (string) ($errors['_result_code'] ?? ScheduledActionResultCode::ExecutionError->value);
        if ($code === ScheduledActionResultCode::ValidationFailed->value) {
            return [
                'kind'    => 'failed',
                'code'    => $code,
                'message' => 'Publish validation failed.',
            ];
        }

        if (in_array($code, self::SKIP_CODES, true)) {
            return [
                'kind'    => 'skipped',
                'code'    => $code,
                'message' => $code,
            ];
        }

        return [
            'kind'    => 'failed',
            'code'    => ScheduledActionResultCode::ExecutionError->value,
            'message' => 'Execution error.',
        ];
    }

    /**
     * @param array{kind: 'applied'|'skipped'|'failed', code: string, message: string} $mutation
     */
    private function persistExecutionResult(int $scheduleId, array $mutation): void
    {
        $now    = $this->utcNow();
        $status = match ($mutation['kind']) {
            'applied' => ScheduledActionStatus::Processed->value,
            'skipped' => ScheduledActionStatus::Skipped->value,
            'failed'  => ScheduledActionStatus::Failed->value,
        };

        $data = [
            'status'         => $status,
            'result_code'    => $mutation['code'],
            'result_message' => $mutation['message'],
            'processed_at'   => $now,
        ];

        if ($mutation['kind'] === 'failed') {
            $data['failed_at']  = $now;
            $data['last_error'] = $mutation['message'];
        }

        $this->scheduledActionModel->update($scheduleId, $data);
    }

    private function markFailed(int $scheduleId, string $message): void
    {
        $now = $this->utcNow();
        $this->scheduledActionModel->update($scheduleId, [
            'status'         => ScheduledActionStatus::Failed->value,
            'result_code'    => ScheduledActionResultCode::ExecutionError->value,
            'result_message' => $message,
            'last_error'     => $message,
            'failed_at'      => $now,
            'processed_at'   => $now,
        ]);
    }

    private function invalidatePublicCache(string $targetType, int $targetId): void
    {
        if ($targetType === 'page') {
            $this->publicContentCache->invalidatePage($targetId);

            return;
        }

        if ($targetType === 'post') {
            $this->publicContentCache->invalidatePost($targetId);
        }
    }

    /**
     * @return array<string, string>|null
     */
    private function authorizeCreate(
        ScheduledActionTargetType $targetType,
        int $targetId,
        ScheduledActionType $actionType,
        User $actor,
    ): ?array {
        $permission = match ($targetType) {
            ScheduledActionTargetType::Page => $actionType === ScheduledActionType::Publish
                ? 'page.publish'
                : 'page.unpublish',
            ScheduledActionTargetType::Post => $actionType === ScheduledActionType::Publish
                ? 'post.publish'
                : 'post.unpublish',
        };

        if (! $actor->can($permission)) {
            return ['_forbidden' => 'You are not allowed to schedule this action.'];
        }

        if ($targetType === ScheduledActionTargetType::Post && ! $this->postService->actorMayWritePost($actor, $targetId)) {
            return ['_forbidden' => 'You are not allowed to schedule this Post.'];
        }

        return null;
    }

    /**
     * @return array<string, string>|null
     */
    private function assertTargetExists(ScheduledActionTargetType $targetType, int $targetId): ?array
    {
        if ($targetType === ScheduledActionTargetType::Page) {
            if ($this->pageService->findById($targetId) === null) {
                return ['target_id' => 'Page not found.'];
            }

            return null;
        }

        if ($this->postService->findById($targetId) === null) {
            return ['target_id' => 'Post not found.'];
        }

        return null;
    }

    /**
     * @return array{utc: string, error: string|null}
     */
    private function localToUtc(string $local): array
    {
        $trimmed = trim($local);
        if ($trimmed === '') {
            return ['utc' => '', 'error' => 'Schedule time is required.'];
        }

        $normalized = str_replace('T', ' ', $trimmed);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized) === 1) {
            $normalized .= ':00';
        }

        try {
            $siteTz = new DateTimeZone($this->siteTimezone());
            $localDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $normalized, $siteTz);
            if ($localDt === false) {
                return ['utc' => '', 'error' => 'Schedule time is invalid.'];
            }

            $utc = $localDt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return ['utc' => '', 'error' => 'Schedule time is invalid.'];
        }

        return ['utc' => $utc, 'error' => null];
    }

    private function utcNow(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    private function utcPlusMinutes(int $minutes): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->add(new DateInterval('PT' . $minutes . 'M'))
            ->format('Y-m-d H:i:s');
    }

    private function isUniquePendingViolation(string $message): bool
    {
        $haystack = strtolower($message);

        return str_contains($haystack, 'uq_scheduled_pending')
            || str_contains($haystack, 'unique')
            || str_contains($haystack, 'duplicate');
    }
}
