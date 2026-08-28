<?php

namespace App\Domain\Academic\Support;

use App\Domain\Academic\Enums\StudentAlertCategory;

final class AlertCopy
{
    public const DISCLAIMER = 'Signalement interne FANABE. Ne constitue pas un diagnostic. Confirmation humaine requise.';

    public static function summary(StudentAlertCategory $category): string
    {
        return match ($category) {
            StudentAlertCategory::GradesDecline => 'Évolution inhabituelle des résultats, nécessitant une attention.',
            StudentAlertCategory::AbsenceIncrease => 'Évolution inhabituelle de la présence, nécessitant une attention.',
            StudentAlertCategory::LatenessPattern => 'Évolution inhabituelle de la ponctualité, nécessitant une attention.',
            StudentAlertCategory::HomeworkDecline => 'Évolution inhabituelle du travail demandé, nécessitant une attention.',
            StudentAlertCategory::Combined => 'Évolution inhabituelle nécessitant une attention.',
        };
    }

    public static function recommendedAction(StudentAlertCategory $category): string
    {
        return match ($category) {
            StudentAlertCategory::AbsenceIncrease => 'Prendre contact avec la famille pour comprendre la situation.',
            default => 'Examiner le dossier et décider d’un suivi humain.',
        };
    }
}
