<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * scheduled_actions.result_code (ADR-021 §14). Not an AuditEvent.
 */
enum ScheduledActionResultCode: string
{
    case Applied                   = 'APPLIED';
    case TargetTrash               = 'TARGET_TRASH';
    case TargetArchived            = 'TARGET_ARCHIVED';
    case TargetPendingReview       = 'TARGET_PENDING_REVIEW';
    case TargetAlreadyPublished    = 'TARGET_ALREADY_PUBLISHED';
    case TargetAlreadyUnpublished  = 'TARGET_ALREADY_UNPUBLISHED';
    case TargetMissing             = 'TARGET_MISSING';
    case InvalidSourceState        = 'INVALID_SOURCE_STATE';
    case LockVersionConflict       = 'LOCK_VERSION_CONFLICT';
    case ValidationFailed          = 'VALIDATION_FAILED';
    case Cancelled                 = 'CANCELLED';
    case ExecutionError            = 'EXECUTION_ERROR';
}
