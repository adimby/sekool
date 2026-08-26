<?php

namespace App\Domain\Finance\Enums;

enum FeeCategory: string
{
    case Tuition = 'tuition';
    case Registration = 'registration';
    case Exam = 'exam';
    case Other = 'other';
}
