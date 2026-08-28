<?php

namespace App\Domain\Identity\Enums;

enum SchoolPersonLinkSource: string
{
    case Created = 'created';
    case ShareToken = 'share_token';
    case PublicIdApproved = 'public_id_approved';
    case StaffAttested = 'staff_attested';
    case Enrollment = 'enrollment';
}
