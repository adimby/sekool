<?php

namespace App\Domain\Certificate\Enums;

enum CertificateType: string
{
    case Enrollment = 'enrollment_certificate';
    case ReportCard = 'report_card';

    public function label(): string
    {
        return match ($this) {
            self::Enrollment => 'Certificat de scolarité',
            self::ReportCard => 'Bulletin',
        };
    }
}
