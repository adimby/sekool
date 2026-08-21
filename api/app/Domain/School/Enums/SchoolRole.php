<?php

namespace App\Domain\School\Enums;

enum SchoolRole: string
{
    case Owner = 'school_owner';
    case Admin = 'school_admin';
    case Principal = 'principal';
    case Teacher = 'teacher';
    case Accountant = 'accountant';
    case Staff = 'staff';
}
