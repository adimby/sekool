<?php

namespace App\Domain\Certificate\Actions;

use App\Domain\Certificate\Enums\CertificateStatus;
use App\Domain\Certificate\Enums\CertificateType;
use App\Domain\Certificate\Models\Certificate;
use App\Domain\Certificate\Models\CertificateVerificationToken;
use App\Domain\Certificate\Ports\DocumentSigner;
use App\Domain\Certificate\Support\CertificateCopy;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\FanabeDocument;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\School\Models\School;
use Illuminate\Support\Facades\Storage;

final class IssueWithdrawalCertificate
{
    public function __construct(private readonly DocumentSigner $signer) {}

    /**
     * @return array{certificate: Certificate, token: string, verify_url: string}
     */
    public function execute(string $schoolId, string $enrollmentId, string $actorPersonId): array
    {
        $enrollment = Enrollment::query()->with(['person', 'classroom', 'schoolYear', 'school'])->find($enrollmentId);
        if ($enrollment === null || (string) $enrollment->school_id !== $schoolId) {
            throw new DomainException('Inscription introuvable.', 404);
        }
        if (! in_array($enrollment->status, [EnrollmentStatus::Withdrawn, EnrollmentStatus::TransferredOut], true)) {
            throw new DomainException('Un certificat de radiation s’émet après une sortie d’effectif.');
        }
        if ($enrollment->person === null) {
            throw new DomainException('Élève introuvable.', 404);
        }

        $school = $enrollment->school ?? School::query()->find($schoolId);
        $issuedOn = now()->toDateString();
        $reference = sprintf('RAD-%s-%s', explode('-', (string) $enrollment->schoolYear?->label)[0] ?? now()->year, strtoupper(bin2hex(random_bytes(3))));

        $snapshot = [
            'type' => CertificateType::Withdrawal->value,
            'type_label' => CertificateType::Withdrawal->label(),
            'school_name' => $school?->name,
            'school_city' => $school?->city,
            'year_label' => $enrollment->schoolYear?->label,
            'classroom_name' => $enrollment->classroom?->name,
            'full_name' => trim($enrollment->person->first_name.' '.$enrollment->person->last_name),
            'first_name' => $enrollment->person->first_name,
            'last_name' => $enrollment->person->last_name,
            'birth_date' => $enrollment->person->birth_date?->toDateString(),
            'ended_on' => $enrollment->ended_on?->toDateString() ?? $issuedOn,
            'exit_reason' => $enrollment->exit_reason,
            'public_reference' => $reference,
            'issued_on' => $issuedOn,
            'disclaimer' => CertificateCopy::DISCLAIMER,
        ];

        $html = CertificateCopy::renderHtml($snapshot);
        $sha = hash('sha256', $html);
        $storageKey = 'schools/'.$schoolId.'/certificates/'.$reference.'.html';
        Storage::disk('local')->put($storageKey, $html);

        $document = FanabeDocument::query()->create([
            'school_id' => $schoolId,
            'owner_person_id' => $enrollment->person_id,
            'type' => CertificateType::Withdrawal->value,
            'source_type' => 'native',
            'issuer_school_id' => $schoolId,
            'verification_status' => 'verified_by_issuer',
            'uploaded_by_person_id' => $actorPersonId,
            'uploaded_at' => now(),
            'storage_key' => $storageKey,
            'sha256' => $sha,
            'byte_size' => strlen($html),
            'mime_type' => 'text/html',
            'version' => 1,
            'provenance' => ['issued_as' => 'withdrawal_certificate'],
        ]);

        $certificate = Certificate::query()->create([
            'school_id' => $schoolId,
            'document_id' => $document->id,
            'subject_person_id' => $enrollment->person_id,
            'enrollment_id' => $enrollment->id,
            'type' => CertificateType::Withdrawal,
            'public_reference' => $reference,
            'issued_at' => now(),
            'status' => CertificateStatus::Valid,
            'template_version' => '1',
            'payload_snapshot' => $snapshot,
            'artifact_sha256' => $sha,
            'signer_key_id' => $this->signer->keyId(),
            'signature' => $this->signer->sign($sha),
        ]);

        $plain = bin2hex(random_bytes(20));
        CertificateVerificationToken::query()->create([
            'school_id' => $schoolId,
            'certificate_id' => $certificate->id,
            'token_hash' => hash('sha256', $plain),
        ]);

        Auditor::record('certificate.issued', 'certificate', $certificate->id, $enrollment->person_id, [
            'type' => CertificateType::Withdrawal->value,
        ]);

        return [
            'certificate' => $certificate,
            'token' => $plain,
            'verify_url' => rtrim((string) config('app.url'), '/').'/verify/'.$plain,
        ];
    }
}
