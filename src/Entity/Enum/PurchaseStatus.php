<?php

namespace App\Entity\Enum;

enum PurchaseStatus: string
{
    case REQUESTED = 'requested';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case PURCHASED = 'purchased';
    case DELIVERED = 'delivered';
    case COMPLETE = 'complete';
    case REIMBURSED = 'reimbursed';
    case CANCELLED = 'cancelled';
}

