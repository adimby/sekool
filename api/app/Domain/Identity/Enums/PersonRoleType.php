<?php

namespace App\Domain\Identity\Enums;

enum PersonRoleType: string
{
    case Student = 'student';
    case Alumni = 'alumni';
    case Parent = 'parent';
    case Guardian = 'guardian';
    case FinancialContact = 'financial_contact';
    case Staff = 'staff';
    case SupplierAgent = 'supplier_agent';
}
