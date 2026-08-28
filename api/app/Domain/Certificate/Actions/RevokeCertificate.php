<?php

namespace App\Domain\Certificate\Actions;

use App\Domain\Certificate\Enums\CertificateStatus;
use App\Domain\Certificate\Models\Certificate;
use App\Domain\Certificate\Models\CertificateVerificationToken;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;

final class RevokeCertificate
{
    public function execute(string $schoolId, string $certificateId, string $reason): Certificate
    {
        $certificate = Certificate::query()->find($certificateId);
        if ($certificate === null || (string) $certificate->school_id !== $schoolId) {
            throw new DomainException('Certificat introuvable.', 404);
        }

        if ($certificate->status === CertificateStatus::Revoked) {
            return $certificate;
        }

        $certificate->forceFill([
            'status' => CertificateStatus::Revoked,
            'revoked_at' => now(),
            'revocation_reason' => trim($reason),
        ])->save();

        CertificateVerificationToken::query()
            ->where('certificate_id', $certificate->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        Auditor::record('certificate.revoked', 'certificate', $certificate->id, $certificate->subject_person_id);

        return $certificate->fresh() ?? $certificate;
    }
}
