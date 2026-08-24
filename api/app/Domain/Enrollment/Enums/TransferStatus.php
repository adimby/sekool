<?php

namespace App\Domain\Enrollment\Enums;

enum TransferStatus: string
{
    case PendingParent = 'pending_parent';
    case PendingOriginSchool = 'pending_origin_school';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
