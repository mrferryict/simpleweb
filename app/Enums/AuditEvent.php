<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Audit event vocabulary (ADR-019 §11.4).
 */
enum AuditEvent: string
{
    case PostCreated            = 'POST_CREATED';
    case PostUpdated            = 'POST_UPDATED';
    case PostSubmittedForReview = 'POST_SUBMITTED_FOR_REVIEW';
    case PostReviewedPublished  = 'POST_REVIEWED_PUBLISHED';
    case PostReturnedForRevision = 'POST_RETURNED_FOR_REVISION';
    case PostPublished          = 'POST_PUBLISHED';
    case PostUnpublished        = 'POST_UNPUBLISHED';
    case PostArchived           = 'POST_ARCHIVED';
    case PostTrashed            = 'POST_TRASHED';
    case PostRestored           = 'POST_RESTORED';
    case PostPermanentlyDeleted = 'POST_PERMANENTLY_DELETED';

    case PageCreated            = 'PAGE_CREATED';
    case PageUpdated            = 'PAGE_UPDATED';
    case PagePublished          = 'PAGE_PUBLISHED';
    case PageUnpublished        = 'PAGE_UNPUBLISHED';
    case PageArchived           = 'PAGE_ARCHIVED';
    case PageTrashed            = 'PAGE_TRASHED';
    case PageRestored           = 'PAGE_RESTORED';
    case PagePermanentlyDeleted = 'PAGE_PERMANENTLY_DELETED';

    case RevisionRestored = 'REVISION_RESTORED';

    case ThemeActivated = 'THEME_ACTIVATED';

    /** DOC-03 §25 / ADR-026 auth & security vocabulary */
    case Login            = 'LOGIN';
    case LoginFailed      = 'LOGIN_FAILED';
    case Logout           = 'LOGOUT';
    case PasswordChanged  = 'PASSWORD_CHANGED';
    case PasswordReset    = 'PASSWORD_RESET';
    case AdminRecovery    = 'ADMIN_RECOVERY';
    case UserCreated      = 'USER_CREATED';
    case UserActivated    = 'USER_ACTIVATED';
    case UserDeactivated  = 'USER_DEACTIVATED';
}
