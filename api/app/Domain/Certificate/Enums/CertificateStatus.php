<?php

namespace App\Domain\Certificate\Enums;

enum CertificateStatus: string
{
    case Valid = 'valid';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
