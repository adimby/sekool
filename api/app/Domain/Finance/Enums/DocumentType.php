<?php

namespace App\Domain\Finance\Enums;

enum DocumentType: string
{
    case Invoice = 'invoice';
    case Receipt = 'receipt';
}
