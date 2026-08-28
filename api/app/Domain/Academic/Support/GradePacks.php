<?php

namespace App\Domain\Academic\Support;

use App\Domain\Academic\Enums\GradeStage;

final class GradePacks
{
    public const PRESCHOOL = 'preschool';

    public const PRIMARY = 'primary';

    public const PRIMARY_MALAGASY = 'primary_malagasy';

    public const MIDDLE = 'middle';

    public const HIGH = 'high';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::catalog());
    }

    /**
     * @return array<string, list<array{name: string, stage: GradeStage, sequence: int}>>
     */
    public static function catalog(): array
    {
        return [
            self::PRESCHOOL => [
                ['name' => 'PS', 'stage' => GradeStage::Preschool, 'sequence' => 1],
                ['name' => 'MS', 'stage' => GradeStage::Preschool, 'sequence' => 2],
                ['name' => 'GS', 'stage' => GradeStage::Preschool, 'sequence' => 3],
            ],
            self::PRIMARY => [
                ['name' => 'CP', 'stage' => GradeStage::Primary, 'sequence' => 11],
                ['name' => 'CE1', 'stage' => GradeStage::Primary, 'sequence' => 12],
                ['name' => 'CE2', 'stage' => GradeStage::Primary, 'sequence' => 13],
                ['name' => 'CM1', 'stage' => GradeStage::Primary, 'sequence' => 14],
                ['name' => 'CM2', 'stage' => GradeStage::Primary, 'sequence' => 15],
            ],
            self::PRIMARY_MALAGASY => [
                ['name' => 'T1', 'stage' => GradeStage::Primary, 'sequence' => 16],
                ['name' => 'T2', 'stage' => GradeStage::Primary, 'sequence' => 17],
                ['name' => 'T3', 'stage' => GradeStage::Primary, 'sequence' => 18],
                ['name' => 'T4', 'stage' => GradeStage::Primary, 'sequence' => 19],
                ['name' => 'T5', 'stage' => GradeStage::Primary, 'sequence' => 20],
            ],
            self::MIDDLE => [
                ['name' => '6ème', 'stage' => GradeStage::Middle, 'sequence' => 21],
                ['name' => '5ème', 'stage' => GradeStage::Middle, 'sequence' => 22],
                ['name' => '4ème', 'stage' => GradeStage::Middle, 'sequence' => 23],
                ['name' => '3ème', 'stage' => GradeStage::Middle, 'sequence' => 24],
            ],
            self::HIGH => [
                ['name' => 'Seconde', 'stage' => GradeStage::High, 'sequence' => 31],
                ['name' => 'Première', 'stage' => GradeStage::High, 'sequence' => 32],
                ['name' => 'Terminale', 'stage' => GradeStage::High, 'sequence' => 33],
            ],
        ];
    }
}
