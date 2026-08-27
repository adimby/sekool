<?php

namespace App\Domain\Finance\Enums;

enum FeeCategory: string
{
    case Tuition = 'tuition';
    case Registration = 'registration';
    case Exam = 'exam';
    case Association = 'association';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Tuition => 'Écolage',
            self::Registration => 'Droit d’inscription',
            self::Exam => 'Examen',
            self::Association => 'Cotisation APE',
            self::Other => 'Autre',
        };
    }
}
