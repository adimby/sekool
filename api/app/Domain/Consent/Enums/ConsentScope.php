<?php

namespace App\Domain\Consent\Enums;

enum ConsentScope: string
{
    case IdentityCore = 'identity.core';
    case IdentityContact = 'identity.contact';
    case AcademicRecords = 'academic.records';
    case AcademicAttendance = 'academic.attendance';
    case FinanceHistory = 'finance.history';
    case DocumentsExternal = 'documents.external';
    case DocumentsCertificates = 'documents.certificates';
}
