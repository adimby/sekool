<?php

namespace App\Domain\Certificate\Support;

use App\Domain\Certificate\Models\Certificate;

final class CertificatePayload
{
    /** @return array<string, mixed> */
    public static function staff(Certificate $certificate, ?string $verifyUrl = null): array
    {
        $certificate->loadMissing(['enrollment.person', 'enrollment.classroom', 'subject']);

        $payload = [
            'id' => $certificate->id,
            'type' => $certificate->type->value,
            'type_label' => $certificate->type->label(),
            'status' => $certificate->effectiveStatus()->value,
            'public_reference' => $certificate->public_reference,
            'issued_at' => $certificate->issued_at?->toDateString(),
            'expires_at' => $certificate->expires_at?->toDateString(),
            'enrollment_id' => $certificate->enrollment_id,
            'student_name' => trim(($certificate->subject?->first_name ?? $certificate->enrollment?->person?->first_name ?? '').' '.($certificate->subject?->last_name ?? $certificate->enrollment?->person?->last_name ?? '')),
            'classroom' => $certificate->enrollment?->classroom?->name,
            'disclaimer' => CertificateCopy::DISCLAIMER,
        ];

        if ($verifyUrl !== null) {
            $payload['verify_url'] = $verifyUrl;
        }

        return $payload;
    }
}
