<?php

namespace App\Domain\School\Support;

use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Enums\SchoolRole;
use App\Domain\School\Models\School;
use App\Domain\School\Models\SchoolRoleAssignment;

final class SchoolDirectoryPayload
{
    /**
     * Annuaire d'établissement pour la plateforme.
     * Jamais d'écolage, de facture, d'effectif ni de dossier élève.
     *
     * @return array<string, mixed>
     */
    public static function for(School $school): array
    {
        $admins = TenantContext::runWithRlsBypass(fn () => SchoolRoleAssignment::query()
            ->withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->whereNull('revoked_at')
            ->whereIn('role', [SchoolRole::Owner, SchoolRole::Admin])
            ->with('person')
            ->get()
            ->map(fn (SchoolRoleAssignment $row): array => [
                'person_id' => $row->person_id,
                'role' => $row->role instanceof SchoolRole ? $row->role->value : (string) $row->role,
                'first_name' => $row->person?->first_name,
                'last_name' => $row->person?->last_name,
                'email' => $row->person?->email,
            ])
            ->values()
            ->all());

        return [
            'id' => $school->id,
            'name' => $school->name,
            'short_name' => $school->short_name,
            'code' => $school->code,
            'city' => $school->city,
            'region' => $school->region,
            'phone_e164' => $school->phone_e164,
            'email' => $school->email,
            'timezone' => $school->timezone,
            'locale' => $school->locale,
            'status' => $school->status,
            'plan' => $school->plan,
            'created_at' => $school->created_at?->toIso8601String(),
            'admins' => $admins,
        ];
    }
}
