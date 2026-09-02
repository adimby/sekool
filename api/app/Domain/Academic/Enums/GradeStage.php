<?php

namespace App\Domain\Academic\Enums;

enum GradeStage: string
{
    case Preschool = 'preschool';
    case Primary = 'primary';
    case Middle = 'middle';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Preschool => 'Maternelle',
            self::Primary => 'Primaire',
            self::Middle => 'Collège',
            self::High => 'Lycée',
        };
    }

    public function allowsDelegate(): bool
    {
        return $this !== self::Preschool;
    }

    public function allowsCouncil(): bool
    {
        return $this === self::Middle || $this === self::High;
    }

    public function unitLabel(): string
    {
        return $this === self::Preschool ? 'Groupe' : 'Classe';
    }

    public function usesLivret(): bool
    {
        return $this === self::Preschool || $this === self::Primary;
    }

    /**
     * Collège / lycée : les élèves restent en salle, les professeurs tournent, l’appel est par créneau.
     * Maternelle / primaire : le même enseignant suit le groupe, l’appel est au jour.
     */
    public function takesAttendanceByPeriod(): bool
    {
        return $this === self::Middle || $this === self::High;
    }
}
