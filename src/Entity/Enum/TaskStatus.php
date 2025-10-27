<?php

namespace App\Entity\Enum;

enum TaskStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';
}

