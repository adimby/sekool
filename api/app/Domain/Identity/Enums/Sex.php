<?php

namespace App\Domain\Identity\Enums;

enum Sex: string
{
    case Female = 'female';
    case Male = 'male';
    case Unspecified = 'unspecified';
}
