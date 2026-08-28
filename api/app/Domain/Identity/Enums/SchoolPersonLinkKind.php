<?php

namespace App\Domain\Identity\Enums;

enum SchoolPersonLinkKind: string
{
    case Parent = 'parent';
    case Student = 'student';
    case Staff = 'staff';
}
