<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Enums\GradeStage;
use App\Domain\Academic\Models\CompetencyDomain;
use App\Domain\Academic\Models\CompetencyItem;

final class EnsureCompetencyCatalog
{
    /**
     * @return list<array{code: string, label: string, items: list<string>}>
     */
    public static function blueprint(GradeStage $stage): array
    {
        return match ($stage) {
            GradeStage::Preschool => [
                [
                    'code' => 'LANGAGE',
                    'label' => 'Langage',
                    'items' => [
                        'S’exprimer à l’oral',
                        'Comprendre un récit',
                        'Découvrir l’écrit',
                    ],
                ],
                [
                    'code' => 'VIVRE',
                    'label' => 'Vivre ensemble',
                    'items' => [
                        'Respecter les autres',
                        'Participer à la vie du groupe',
                    ],
                ],
                [
                    'code' => 'MOTRICITE',
                    'label' => 'Motricité',
                    'items' => [
                        'Se déplacer avec aisance',
                        'Contrôler ses gestes',
                    ],
                ],
                [
                    'code' => 'DECOUVERTE',
                    'label' => 'Découverte du monde',
                    'items' => [
                        'Observer le vivant',
                        'Se repérer dans l’espace et le temps',
                    ],
                ],
            ],
            GradeStage::Primary => [
                [
                    'code' => 'MALAGASY',
                    'label' => 'Malagasy',
                    'items' => [
                        'Comprendre un texte',
                        'S’exprimer à l’écrit',
                    ],
                ],
                [
                    'code' => 'FRANCAIS',
                    'label' => 'Français',
                    'items' => [
                        'Comprendre un texte',
                        'S’exprimer à l’écrit',
                    ],
                ],
                [
                    'code' => 'MATHS',
                    'label' => 'Mathématiques',
                    'items' => [
                        'Nombres et calcul',
                        'Grandeurs et mesures',
                        'Espace et géométrie',
                    ],
                ],
                [
                    'code' => 'MONDE',
                    'label' => 'Questionner le monde',
                    'items' => [
                        'Le vivant',
                        'L’espace et le temps',
                    ],
                ],
                [
                    'code' => 'EPS',
                    'label' => 'EPS',
                    'items' => [
                        'Participer à une activité physique',
                    ],
                ],
            ],
            default => [],
        };
    }

    public function forSchool(string $schoolId, GradeStage $stage): void
    {
        if (! $stage->usesLivret()) {
            return;
        }

        if (CompetencyDomain::query()->where('school_id', $schoolId)->where('stage', $stage)->exists()) {
            return;
        }

        foreach (self::blueprint($stage) as $domainIndex => $domainSpec) {
            $domain = CompetencyDomain::query()->create([
                'school_id' => $schoolId,
                'stage' => $stage,
                'code' => $domainSpec['code'],
                'label' => $domainSpec['label'],
                'sequence' => $domainIndex + 1,
            ]);

            foreach ($domainSpec['items'] as $itemIndex => $label) {
                CompetencyItem::query()->create([
                    'school_id' => $schoolId,
                    'domain_id' => $domain->id,
                    'label' => $label,
                    'sequence' => $itemIndex + 1,
                ]);
            }
        }
    }
}
