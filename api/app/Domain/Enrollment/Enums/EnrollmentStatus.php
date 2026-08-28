<?php

namespace App\Domain\Enrollment\Enums;

enum EnrollmentStatus: string
{
    case PreRegistered = 'pre_registered';
    case Active = 'active';
    case Suspended = 'suspended';
    case TransferredOut = 'transferred_out';
    case Graduated = 'graduated';
    case Withdrawn = 'withdrawn';
}
