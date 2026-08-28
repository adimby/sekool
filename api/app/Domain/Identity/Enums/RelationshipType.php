<?php

namespace App\Domain\Identity\Enums;

enum RelationshipType: string
{
    case ParentOf = 'parent_of';
    case GuardianOf = 'guardian_of';
    case FinancialContactFor = 'financial_contact_for';
    case PickupAuthorizedFor = 'pickup_authorized_for';
}
