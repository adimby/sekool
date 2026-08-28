<?php

namespace App\Domain\Certificate\Actions;

use App\Domain\Certificate\Enums\CertificateStatus;
use App\Domain\Certificate\Models\CertificateVerification;
use App\Domain\Certificate\Models\CertificateVerificationToken;
use App\Domain\Certificate\Support\CertificateCopy;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\Request;

final class VerifyCertificateToken
{
    /**
     * @return array<string, mixed>
     */
    public function execute(string $token, ?string $birthDate = null): array
    {
        return TenantContext::runWithRlsBypass(function () use ($token, $birthDate): array {
            $hash = hash('sha256', $token);
            $row = CertificateVerificationToken::query()
                ->withoutGlobalScopes()
                ->with('certificate.school')
                ->where('token_hash', $hash)
                ->first();

            $ipHash = hash('sha256', (string) Request::ip());
            $uaHash = hash('sha256', (string) Request::userAgent());

            if ($row === null || $row->certificate === null) {
                return [
                    'status' => 'UNKNOWN',
                    'disclaimer' => CertificateCopy::DISCLAIMER,
                ];
            }

            $certificate = $row->certificate;
            $status = $certificate->effectiveStatus();

            if ($status === CertificateStatus::Valid && ! $row->isActive()) {
                CertificateVerification::query()->create([
                    'school_id' => $row->school_id,
                    'token_id' => $row->id,
                    'verified_at' => now(),
                    'ip_hash' => $ipHash,
                    'user_agent_hash' => $uaHash,
                    'outcome' => 'unknown',
                ]);

                return [
                    'status' => 'UNKNOWN',
                    'disclaimer' => CertificateCopy::DISCLAIMER,
                ];
            }

            $snapshot = $certificate->payload_snapshot ?? [];
            $fullNameRevealed = false;
            $displayName = CertificateCopy::maskedName(
                (string) ($snapshot['first_name'] ?? ''),
                (string) ($snapshot['last_name'] ?? ''),
            );

            if (is_string($birthDate) && $birthDate !== '' && isset($snapshot['birth_date']) && $snapshot['birth_date'] === $birthDate) {
                $displayName = (string) ($snapshot['full_name'] ?? $displayName);
                $fullNameRevealed = true;
            }

            $outcome = match ($status) {
                CertificateStatus::Valid => 'valid',
                CertificateStatus::Revoked => 'revoked',
                CertificateStatus::Expired => 'expired',
            };

            CertificateVerification::query()->create([
                'school_id' => $row->school_id,
                'token_id' => $row->id,
                'verified_at' => now(),
                'ip_hash' => $ipHash,
                'user_agent_hash' => $uaHash,
                'outcome' => $outcome,
            ]);

            return [
                'status' => strtoupper($outcome),
                'type' => $certificate->type instanceof \BackedEnum ? $certificate->type->value : (string) $certificate->type,
                'type_label' => is_object($certificate->type) && method_exists($certificate->type, 'label')
                    ? $certificate->type->label()
                    : 'Certificat',
                'issuer' => $certificate->school?->name,
                'issued_on' => $snapshot['issued_on'] ?? $certificate->issued_at?->toDateString(),
                'year_label' => $snapshot['year_label'] ?? null,
                'classroom_name' => $snapshot['classroom_name'] ?? null,
                'person' => $displayName,
                'full_name_revealed' => $fullNameRevealed,
                'public_reference' => $certificate->public_reference,
                'disclaimer' => CertificateCopy::DISCLAIMER,
            ];
        });
    }
}
