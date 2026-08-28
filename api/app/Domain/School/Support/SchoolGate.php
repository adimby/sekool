<?php

namespace App\Domain\School\Support;

use App\Domain\Academic\Models\Classroom;
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

    public const TEACHER = [
        SchoolRole::Teacher->value,
    ];

    public const CLASSROOM = [
        SchoolRole::Owner->value,
        SchoolRole::Admin->value,
        SchoolRole::Principal->value,
        SchoolRole::Teacher->value,
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
            'teacher' => self::TEACHER,
            'classroom' => self::CLASSROOM,
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

        return self::visibleClassrooms($request)
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

        return self::visibleClassrooms($request)
            ->pluck('grade_level_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function canViewClassroom(Request $request, Classroom $classroom): bool
    {
        if (self::isDirection($request)) {
            return true;
        }

        return self::teaches($request, $classroom);
    }

    public static function canTakeAttendance(Request $request, Classroom $classroom): bool
    {
        return self::teaches($request, $classroom);
    }

    public static function teaches(Request $request, Classroom $classroom): bool
    {
        $personId = $request->user()?->person_id;

        return self::isTeacher($request)
            && is_string($personId)
            && $personId !== ''
            && $classroom->main_teacher_person_id === $personId;
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
            return $query->where('main_teacher_person_id', $personId);
        }

        return $query->whereRaw('false');
    }
}
