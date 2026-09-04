<?php

namespace App\Domain\School\Support;

use App\Domain\School\Enums\SchoolRole;

final class SchoolCapabilities
{
    /**
     * Menus and writes for one school membership. Fail-closed: unknown role ⇒ nothing.
     *
     * @param  list<string>  $roles
     * @return array{
     *     accueil: bool,
     *     famille: bool,
     *     classe: bool,
     *     finance: bool,
     *     caisse: bool,
     *     kits: bool,
     *     indices: bool,
     *     appel: bool,
     *     vie: bool,
     *     notes: bool,
     *     titulaire: bool,
     *     enseigne: bool
     * }
     */
    public static function for(array $roles, bool $titulaire, bool $enseigne): array
    {
        $org = self::has($roles, SchoolRole::Owner, SchoolRole::Admin, SchoolRole::Principal);
        $ownerAdmin = self::has($roles, SchoolRole::Owner, SchoolRole::Admin);
        $accountant = in_array(SchoolRole::Accountant->value, $roles, true);
        $teacher = in_array(SchoolRole::Teacher->value, $roles, true);

        return [
            'accueil' => $org,
            'famille' => $org,
            'classe' => $org,
            'finance' => $org || $accountant,
            'caisse' => $ownerAdmin || $accountant,
            'kits' => $org || $accountant || $titulaire,
            'indices' => $org,
            'appel' => $teacher,
            'vie' => $teacher && $titulaire,
            'notes' => $teacher && $enseigne && ! $titulaire,
            'titulaire' => $titulaire,
            'enseigne' => $enseigne,
        ];
    }

    private static function has(array $roles, SchoolRole ...$wanted): bool
    {
        foreach ($wanted as $role) {
            if (in_array($role->value, $roles, true)) {
                return true;
            }
        }

        return false;
    }
}
