<?php

namespace App\Domain\School\Support;

use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\Classroom;
use App\Domain\Academic\Models\TimetableSlot;
use App\Domain\Academic\Models\TimetableSubstitution;
use App\Domain\Academic\Support\ClassroomCycle;
use App\Domain\Academic\Support\TimetableDuty;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Enums\SchoolRole;
use App\Domain\School\Models\SchoolRoleAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * RBAC for a school staff session. Fail-closed: an unknown group or missing role is a refusal.
 * Does not read Reliability or Collection scores.
 */
final class SchoolGate
{
    public const DIRECTION = [
        SchoolRole::Owner->value,
        SchoolRole::Admin->value,
        SchoolRole::Principal->value,
    ];

    public const FINANCE = [
        SchoolRole::Owner->value,
        SchoolRole::Admin->value,
        SchoolRole::Principal->value,
        SchoolRole::Accountant->value,
    ];

    /** Record payments, expenses, invoices, collection actions — not the principal. */
    public const FINANCE_WRITE = [
        SchoolRole::Owner->value,
        SchoolRole::Admin->value,
        SchoolRole::Accountant->value,
    ];

    public const TEACHER = [
        SchoolRole::Teacher->value,
    ];

    public const CLASSROOM = [
        SchoolRole::Owner->value,
        SchoolRole::Admin->value,
        SchoolRole::Principal->value,
        SchoolRole::Teacher->value,
    ];

    public const KITS = [
        SchoolRole::Owner->value,
        SchoolRole::Admin->value,
        SchoolRole::Principal->value,
        SchoolRole::Teacher->value,
        SchoolRole::Accountant->value,
    ];

    public const STAFF = [
        SchoolRole::Owner->value,
        SchoolRole::Admin->value,
        SchoolRole::Principal->value,
        SchoolRole::Teacher->value,
        SchoolRole::Accountant->value,
        SchoolRole::Staff->value,
    ];

    /**
     * @return list<string>
     */
    public static function roles(Request $request): array
    {
        $personId = $request->user()?->person_id;
        $schoolId = TenantContext::schoolId();

        if (! is_string($personId) || $personId === '' || ! is_string($schoolId) || $schoolId === '') {
            return [];
        }

        return SchoolRoleAssignment::query()
            ->withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('person_id', $personId)
            ->whereNull('revoked_at')
            ->pluck('role')
            ->map(fn (mixed $role): string => $role instanceof SchoolRole ? $role->value : (string) $role)
            ->unique()
            ->values()
            ->all();
    }

    public static function hasAny(Request $request, string ...$roles): bool
    {
        if ($roles === []) {
            return false;
        }

        return array_intersect(self::roles($request), $roles) !== [];
    }

    public static function assertGroup(Request $request, string $group): void
    {
        $roles = match ($group) {
            'direction' => self::DIRECTION,
            'finance' => self::FINANCE,
            'finance.write' => self::FINANCE_WRITE,
            'teacher' => self::TEACHER,
            'classroom' => self::CLASSROOM,
            'kits' => self::KITS,
            'staff' => self::STAFF,
            default => [],
        };

        if ($roles === [] || ! self::hasAny($request, ...$roles)) {
            abort(403, 'Cette action n’appartient pas à votre espace.');
        }
    }

    public static function isDirection(Request $request): bool
    {
        return self::hasAny($request, ...self::DIRECTION);
    }

    public static function isTeacher(Request $request): bool
    {
        return self::hasAny($request, ...self::TEACHER);
    }

    public static function isFinance(Request $request): bool
    {
        return self::hasAny($request, ...self::FINANCE);
    }

    /**
     * Titulaire of a class of this grade, or direction / service achat.
     */
    public static function canEditKit(Request $request, string $gradeLevelId): bool
    {
        if (self::isDirection($request) || self::isFinance($request)) {
            return true;
        }

        return self::titulaireClassrooms($request)
            ->where('grade_level_id', $gradeLevelId)
            ->exists();
    }

    /**
     * @return list<string>|null null = every grade (direction / finance)
     */
    public static function visibleKitGradeIds(Request $request): ?array
    {
        if (self::isDirection($request) || self::isFinance($request)) {
            return null;
        }

        return self::titulaireClassrooms($request)
            ->pluck('grade_level_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Dossier complet (effectif, EDT, conseil, notes, historique de l’année).
     * Direction, or the titulaire of that class — not a subject teacher.
     */
    public static function canViewClassFile(Request $request, Classroom $classroom): bool
    {
        if (self::isDirection($request)) {
            return true;
        }

        return self::isTitulaireOf($request, $classroom);
    }

    public static function isTitulaireOf(Request $request, Classroom $classroom): bool
    {
        $personId = $request->user()?->person_id;

        return self::isTeacher($request)
            && is_string($personId)
            && $personId !== ''
            && $classroom->main_teacher_person_id === $personId;
    }

    /**
     * Homework / discipline / notes write paths: still the teachers of that class.
     */
    public static function canViewClassroom(Request $request, Classroom $classroom): bool
    {
        if (self::isDirection($request)) {
            return true;
        }

        return self::teaches($request, $classroom);
    }

    public static function canReadAttendanceRoster(
        Request $request,
        Classroom $classroom,
        ?TimetableSlot $slot = null,
        ?string $date = null,
    ): bool {
        if (self::isDirection($request)) {
            return true;
        }

        if (self::isTitulaireOf($request, $classroom)) {
            return true;
        }

        if ($slot === null && self::teaches($request, $classroom)) {
            return true;
        }

        return self::canTakeAttendance($request, $classroom, $slot, $date);
    }

    public static function canTakeAttendance(
        Request $request,
        Classroom $classroom,
        ?TimetableSlot $slot = null,
        ?string $date = null,
    ): bool {
        if (! self::isTeacher($request)) {
            return false;
        }

        if ($slot !== null && $date !== null) {
            $personId = $request->user()?->person_id;
            if (! is_string($personId) || $personId === '') {
                return false;
            }

            if ((string) $slot->classroom_id !== (string) $classroom->id) {
                return false;
            }

            $effective = TimetableDuty::effectiveTeacherPersonId($slot, $date);

            return is_string($effective) && $effective === $personId;
        }

        if (ClassroomCycle::requiresCourseForAttendance($classroom)) {
            return false;
        }

        return self::isAssignedToClass($request, $classroom);
    }

    /**
     * Titulaire, enseignant de matière, ou professeur d’un créneau (y compris remplaçant).
     */
    public static function teaches(Request $request, Classroom $classroom): bool
    {
        $personId = $request->user()?->person_id;

        if (! self::isTeacher($request) || ! is_string($personId) || $personId === '') {
            return false;
        }

        if ($classroom->main_teacher_person_id === $personId) {
            return true;
        }

        if ($classroom->teachers()->where('person_id', $personId)->exists()) {
            return true;
        }

        if ($classroom->timetableSlots()->where('teacher_person_id', $personId)->exists()) {
            return true;
        }

        if (! TimetableSubstitution::tableReady()) {
            return false;
        }

        return $classroom->timetableSlots()
            ->whereHas('substitutions', fn (Builder $query) => $query->where('substitute_person_id', $personId))
            ->exists();
    }

    public static function isAssignedToClass(Request $request, Classroom $classroom): bool
    {
        $personId = $request->user()?->person_id;

        if (! self::isTeacher($request) || ! is_string($personId) || $personId === '') {
            return false;
        }

        if ($classroom->main_teacher_person_id === $personId) {
            return true;
        }

        return $classroom->teachers()->where('person_id', $personId)->exists();
    }

    /**
     * @return Builder<Classroom>
     */
    public static function titulaireClassrooms(Request $request): Builder
    {
        $query = Classroom::query()->with('gradeLevel')->orderBy('name');
        $personId = $request->user()?->person_id;

        if (self::isTeacher($request) && is_string($personId) && $personId !== '') {
            return $query->where('main_teacher_person_id', $personId);
        }

        return $query->whereRaw('false');
    }

    /**
     * @return Builder<Classroom>
     */
    public static function visibleClassrooms(Request $request): Builder
    {
        $query = Classroom::query()->with('gradeLevel')->orderBy('name');

        if (self::isDirection($request) || self::isFinance($request)) {
            return $query;
        }

        $personId = $request->user()?->person_id;
        if (self::isTeacher($request) && is_string($personId) && $personId !== '') {
            return $query->where(function (Builder $inner) use ($personId): void {
                $inner->where('main_teacher_person_id', $personId)
                    ->orWhereHas('teachers', fn (Builder $teachers) => $teachers->where('person_id', $personId))
                    ->orWhereHas('timetableSlots', fn (Builder $slots) => $slots->where('teacher_person_id', $personId));
            });
        }

        return $query->whereRaw('false');
    }

    /**
     * Classes where the teacher takes a day roll (maternelle / primaire, or collège without EDT yet).
     *
     * @return Builder<Classroom>
     */
    public static function dayAttendanceClassrooms(Request $request): Builder
    {
        return self::visibleClassrooms($request)->where(function (Builder $query): void {
            $query->whereHas(
                'gradeLevel',
                fn (Builder $grade) => $grade->whereIn('stage', [
                    GradeStage::Preschool->value,
                    GradeStage::Primary->value,
                ]),
            )->orWhereDoesntHave('timetableSlots');
        });
    }
}
