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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class IssueEnrollmentCertificate
{
    public function __construct(private readonly DocumentSigner $signer) {}

    /**
     * @return array{certificate: Certificate, token: string, verify_url: string}
     */
    public function execute(string $schoolId, string $enrollmentId, string $actorPersonId): array
    {
        return DB::transaction(function () use ($schoolId, $enrollmentId, $actorPersonId): array {
            $enrollment = Enrollment::query()->with(['person', 'classroom', 'schoolYear', 'school'])->find($enrollmentId);
            if ($enrollment === null || (string) $enrollment->school_id !== $schoolId) {
                throw new DomainException('Inscription introuvable.', 404);
            }
            if ($enrollment->status !== EnrollmentStatus::Active) {
                throw new DomainException('Un certificat de scolarité ne s’émet que pour une inscription active.');
            }
            if ($enrollment->person === null) {
                throw new DomainException('Élève introuvable.', 404);
            }

            $school = $enrollment->school ?? School::query()->find($schoolId);
            $issuedOn = now()->toDateString();
            $reference = sprintf('CRT-%s-%s', explode('-', (string) $enrollment->schoolYear?->label)[0] ?? now()->year, strtoupper(bin2hex(random_bytes(3))));

            $snapshot = [
                'type' => CertificateType::Enrollment->value,
                'type_label' => CertificateType::Enrollment->label(),
                'school_name' => $school?->name,
                'school_city' => $school?->city,
                'year_label' => $enrollment->schoolYear?->label,
                'classroom_name' => $enrollment->classroom?->name,
                'full_name' => trim($enrollment->person->first_name.' '.$enrollment->person->last_name),
                'first_name' => $enrollment->person->first_name,
                'last_name' => $enrollment->person->last_name,
                'birth_date' => $enrollment->person->birth_date?->toDateString(),
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
                'type' => CertificateType::Enrollment->value,
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
                'provenance' => ['issued_as' => 'enrollment_certificate'],
            ]);

            $certificate = Certificate::query()->create([
                'school_id' => $schoolId,
                'document_id' => $document->id,
                'subject_person_id' => $enrollment->person_id,
                'enrollment_id' => $enrollment->id,
                'type' => CertificateType::Enrollment,
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

            Auditor::record('certificate.issued', 'certificate', $certificate->id, $enrollment->person_id);

            $verifyUrl = rtrim((string) config('app.url'), '/').'/verify/'.$plain;

            return [
                'certificate' => $certificate,
                'token' => $plain,
                'verify_url' => $verifyUrl,
            ];
        });
    }
}
