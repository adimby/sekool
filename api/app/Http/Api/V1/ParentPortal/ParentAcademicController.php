<?php

namespace App\Http\Api\V1\ParentPortal;

use App\Domain\Academic\Actions\BulletinPayload;
use App\Domain\Academic\Actions\EnsureCompetencyCatalog;
use App\Domain\Academic\Models\CompetencyAssessment;
use App\Domain\Academic\Models\CompetencyDomain;
use App\Domain\Academic\Support\ClassroomCycle;
use App\Domain\Academic\Support\CompetencyPayload;
use App\Domain\Certificate\Models\Certificate;
use App\Domain\Certificate\Support\CertificatePayload;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Support\ParentAuthorization;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ParentAcademicController extends Controller
{
    public function bulletin(Request $request, string $person, BulletinPayload $bulletin): JsonResponse
    {
        if (! ParentAuthorization::isLegalGuardianOf((string) $request->user()->person_id, $person)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return TenantContext::runWithRlsBypass(function () use ($person, $bulletin): JsonResponse {
            $enrollment = Enrollment::query()
                ->withoutGlobalScopes()
                ->where('person_id', $person)
                ->where('status', EnrollmentStatus::Active)
                ->first();

            if ($enrollment === null) {
                return response()->json(['message' => 'Not found.'], 404);
            }

            return response()->json(['data' => $bulletin->forEnrollment($enrollment)]);
        });
    }

    public function competencies(Request $request, string $person, EnsureCompetencyCatalog $catalog): JsonResponse
    {
        if (! ParentAuthorization::isLegalGuardianOf((string) $request->user()->person_id, $person)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return TenantContext::runWithRlsBypass(function () use ($person, $catalog): JsonResponse {
            $enrollment = Enrollment::query()
                ->withoutGlobalScopes()
                ->with('classroom.gradeLevel')
                ->where('person_id', $person)
                ->where('status', EnrollmentStatus::Active)
                ->first();

            if ($enrollment === null || $enrollment->classroom === null) {
                return response()->json(CompetencyPayload::livret([], [], false));
            }

            $stage = ClassroomCycle::of($enrollment->classroom);
            if (! $stage->usesLivret()) {
                return response()->json(CompetencyPayload::livret([], [], false));
            }

            $catalog->forSchool((string) $enrollment->school_id, $stage);

            $domains = CompetencyDomain::query()
                ->withoutGlobalScopes()
                ->with('items')
                ->where('school_id', $enrollment->school_id)
                ->where('stage', $stage)
                ->orderBy('sequence')
                ->get();

            $assessments = CompetencyAssessment::query()
                ->withoutGlobalScopes()
                ->where('enrollment_id', $enrollment->id)
                ->orderByDesc('assessed_on')
                ->get();

            return response()->json(CompetencyPayload::livret($domains, $assessments));
        });
    }

    public function certificates(Request $request, string $person): JsonResponse
    {
        if (! ParentAuthorization::isLegalGuardianOf((string) $request->user()->person_id, $person)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return TenantContext::runWithRlsBypass(function () use ($person): JsonResponse {
            $rows = Certificate::query()
                ->withoutGlobalScopes()
                ->with(['enrollment.person', 'enrollment.classroom', 'subject'])
                ->where('subject_person_id', $person)
                ->orderByDesc('issued_at')
                ->get();

            return response()->json([
                'data' => $rows->map(fn (Certificate $row): array => CertificatePayload::staff($row))->values(),
            ]);
        });
    }
}
