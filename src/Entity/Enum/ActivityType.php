<?php

namespace App\Entity\Enum;

enum ActivityType: string
{
    case COMMENT = 'comment';
    case CONTRIBUTED = 'contributed';
    case CREATED = 'created';
    case EDITED = 'edited';
    case DONE = 'done';
    case UNDONE = 'undone';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';
    case INSPECTED_APPROVED = 'inspected_approved';
    case INSPECTED_REJECTED = 'inspected_rejected';
    case APPLICATION_CANCELLED = 'application_cancelled';
    case IMPACT_CHANGED = 'impact_changed';
    case TASK_LINKED = 'task_linked';
    case TASK_UNLINKED = 'task_unlinked';
    case REOPENED = 'reopened';
}

