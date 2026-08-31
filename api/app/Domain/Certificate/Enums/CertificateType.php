<?php

namespace App\Domain\Certificate\Enums;

enum CertificateType: string
{
    case Enrollment = 'enrollment_certificate';
    case ReportCard = 'report_card';
    case Withdrawal = 'withdrawal_certificate';

    public function label(): string
    {
        return match ($this) {
            self::Enrollment => 'Certificat de scolarité',
            self::ReportCard => 'Bulletin',
            self::Withdrawal => 'Certificat de radiation',
        };
    }
}
