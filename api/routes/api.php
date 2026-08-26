<?php

use App\Http\Api\V1\Auth\ClaimInvitationController;
use App\Http\Api\V1\Auth\LoginController;
use App\Http\Api\V1\Auth\MeController;
use App\Http\Api\V1\ParentPortal\AccessLogController;
use App\Http\Api\V1\ParentPortal\ChildAttendanceController;
use App\Http\Api\V1\ParentPortal\ChildFinanceController;
use App\Http\Api\V1\ParentPortal\ChildrenController;
use App\Http\Api\V1\ParentPortal\ConsentController;
use App\Http\Api\V1\ParentPortal\LinkRequestController;
use App\Http\Api\V1\ParentPortal\ShareTokenController;
use App\Http\Api\V1\ParentPortal\TransferController as ParentTransferController;
use App\Http\Api\V1\School\AcademicHistoryController;
use App\Http\Api\V1\School\AssignClassroomController;
use App\Http\Api\V1\School\AttendanceController;
use App\Http\Api\V1\School\ClassroomController;
use App\Http\Api\V1\School\EnrollmentController;
use App\Http\Api\V1\School\FamilyController;
use App\Http\Api\V1\School\GradeLevelController;
use App\Http\Api\V1\School\InvoiceController;
use App\Http\Api\V1\School\PaymentController;
use App\Http\Api\V1\School\PeopleController;
use App\Http\Api\V1\School\PersonLinkRequestController;
use App\Http\Api\V1\School\SchoolYearController;
use App\Http\Api\V1\School\ShareTokenRedeemController;
use App\Http\Api\V1\School\TransferController;
use App\Http\Middleware\SetPersonContext;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', LoginController::class);
Route::post('/auth/invitations/claim', ClaimInvitationController::class)->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->get('/me', MeController::class);

Route::middleware(['auth:sanctum', SetTenantContext::class])->group(function (): void {
    Route::get('/schools/{school}/years', [SchoolYearController::class, 'index']);
    Route::post('/schools/{school}/years', [SchoolYearController::class, 'store']);
    Route::get('/schools/{school}/years/{year}', [SchoolYearController::class, 'show']);

    Route::get('/schools/{school}/grade-levels', [GradeLevelController::class, 'index']);
    Route::post('/schools/{school}/grade-levels', [GradeLevelController::class, 'store']);

    Route::get('/schools/{school}/classrooms', [ClassroomController::class, 'index']);
    Route::post('/schools/{school}/classrooms', [ClassroomController::class, 'store']);
    Route::get('/schools/{school}/classrooms/{classroom}/roster', [ClassroomController::class, 'roster']);

    Route::post('/schools/{school}/families', [FamilyController::class, 'store']);
    Route::get('/schools/{school}/people', [PeopleController::class, 'index']);
    Route::get('/schools/{school}/people/{person}', [PeopleController::class, 'show']);
    Route::get('/schools/{school}/people/{person}/academic-history', AcademicHistoryController::class);

    Route::get('/schools/{school}/enrollments', [EnrollmentController::class, 'index']);
    Route::post('/schools/{school}/enrollments', [EnrollmentController::class, 'store']);
    Route::post('/schools/{school}/enrollments/{enrollment}/assign-classroom', AssignClassroomController::class);
    Route::post('/schools/{school}/enrollments/{enrollment}/invoices', [InvoiceController::class, 'store']);
    Route::get('/schools/{school}/enrollments/{enrollment}/invoice', [InvoiceController::class, 'show']);

    Route::get('/schools/{school}/attendance', [AttendanceController::class, 'index']);
    Route::post('/schools/{school}/attendance', [AttendanceController::class, 'store']);

    Route::get('/schools/{school}/fee-schedules', [InvoiceController::class, 'schedules']);
    Route::post('/schools/{school}/payments', [PaymentController::class, 'store']);
    Route::get('/schools/{school}/payments/export', [PaymentController::class, 'export']);

    Route::post('/schools/{school}/share-tokens/redeem', ShareTokenRedeemController::class);
    Route::post('/schools/{school}/person-link-requests', [PersonLinkRequestController::class, 'store'])
        ->middleware('throttle:30,1');

    Route::get('/schools/{school}/transfers', [TransferController::class, 'index']);
    Route::post('/schools/{school}/transfers/{transfer}/approve', [TransferController::class, 'approve']);
    Route::post('/schools/{school}/transfers/{transfer}/refuse', [TransferController::class, 'refuse']);
});

Route::middleware(['auth:sanctum', SetPersonContext::class])->prefix('parent')->group(function (): void {
    Route::get('/children', [ChildrenController::class, 'index']);
    Route::get('/children/{person}', [ChildrenController::class, 'show']);
    Route::get('/children/{person}/finance', ChildFinanceController::class);
    Route::get('/children/{person}/attendance', ChildAttendanceController::class);
    Route::post('/share-tokens', [ShareTokenController::class, 'store']);

    Route::get('/link-requests', [LinkRequestController::class, 'index']);
    Route::post('/link-requests/{linkRequest}/approve', [LinkRequestController::class, 'approve']);
    Route::post('/link-requests/{linkRequest}/refuse', [LinkRequestController::class, 'refuse']);

    Route::post('/transfers/{transfer}/approve', [ParentTransferController::class, 'approve']);
    Route::post('/transfers/{transfer}/refuse', [ParentTransferController::class, 'refuse']);

    Route::get('/consents', [ConsentController::class, 'index']);
    Route::post('/consents', [ConsentController::class, 'store']);
    Route::post('/consents/{consent}/revoke', [ConsentController::class, 'revoke']);

    Route::get('/access-log', AccessLogController::class);
});
