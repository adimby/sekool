<?php

namespace App\Http\Api\V1\ParentPortal;

use App\Domain\Academic\Models\ClassPost;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\DisciplinaryCase;
use App\Domain\Academic\Models\SchoolEvent;
use App\Domain\Academic\Support\ClassLifePayload;
use App\Domain\Enrollment\Enums\EnrollmentStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Support\ParentAuthorization;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ParentClassLifeController extends Controller
{
    public function posts(Request $request, string $person): JsonResponse
    {
        if (! ParentAuthorization::isLegalGuardianOf((string) $request->user()->person_id, $person)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return TenantContext::runWithRlsBypass(function () use ($person): JsonResponse {
            $classroomId = $this->activeClassroomId($person);
            if ($classroomId === null) {
                return response()->json(['data' => []]);
            }

            $rows = ClassPost::query()
                ->withoutGlobalScopes()
                ->where('classroom_id', $classroomId)
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'data' => $rows->map(fn (ClassPost $row): array => ClassLifePayload::post($row))->values(),
            ]);
        });
    }

    public function discipline(Request $request, string $person): JsonResponse
    {
        if (! ParentAuthorization::isLegalGuardianOf((string) $request->user()->person_id, $person)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return TenantContext::runWithRlsBypass(function () use ($person): JsonResponse {
            $enrollmentIds = Enrollment::query()
                ->withoutGlobalScopes()
                ->where('person_id', $person)
                ->where('status', EnrollmentStatus::Active)
                ->pluck('id');

            $rows = DisciplinaryCase::query()
                ->withoutGlobalScopes()
                ->with(['enrollment.person'])
                ->whereIn('enrollment_id', $enrollmentIds)
                ->orderByDesc('occurred_on')
                ->get();

            return response()->json([
                'data' => $rows->map(fn (DisciplinaryCase $row): array => ClassLifePayload::disciplinaryCase($row, true))->values(),
            ]);
        });
    }

    public function events(Request $request, string $person): JsonResponse
    {
        if (! ParentAuthorization::isLegalGuardianOf((string) $request->user()->person_id, $person)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return TenantContext::runWithRlsBypass(function () use ($person): JsonResponse {
            $enrollment = Enrollment::query()
                ->withoutGlobalScopes()
                ->where('person_id', $person)
                ->where('status', EnrollmentStatus::Active)
                ->first();

            if ($enrollment === null) {
                return response()->json(['data' => []]);
            }

            $classroom = $enrollment->classroom_id === null
                ? null
                : Classroom::query()->withoutGlobalScopes()->find($enrollment->classroom_id);

            $rows = SchoolEvent::query()
                ->withoutGlobalScopes()
                ->where('school_id', $enrollment->school_id)
                ->where(function (Builder $query) use ($classroom): void {
                    $query->where('audience', 'school');
                    if ($classroom !== null) {
                        $query->orWhere(function (Builder $gradeQuery) use ($classroom): void {
                            $gradeQuery->where('audience', 'grade')
                                ->where('grade_level_id', $classroom->grade_level_id);
                        })->orWhere(function (Builder $classQuery) use ($classroom): void {
                            $classQuery->where('audience', 'classroom')
                                ->where('classroom_id', $classroom->id);
                        });
                    }
                })
                ->orderByDesc('starts_on')
                ->get();

            return response()->json([
                'data' => $rows->map(fn (SchoolEvent $row): array => ClassLifePayload::event($row))->values(),
            ]);
        });
    }

    private function activeClassroomId(string $personId): ?string
    {
        $id = Enrollment::query()
            ->withoutGlobalScopes()
            ->where('person_id', $personId)
            ->where('status', EnrollmentStatus::Active)
            ->value('classroom_id');

        return is_string($id) && $id !== '' ? $id : null;
    }
}
