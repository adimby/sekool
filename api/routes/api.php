<?php

use App\Http\Api\V1\Auth\LoginController;
use App\Http\Api\V1\School\SchoolYearController;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', LoginController::class);

Route::middleware(['auth:sanctum', SetTenantContext::class])->group(function (): void {
    Route::get('/schools/{school}/years', [SchoolYearController::class, 'index']);
    Route::post('/schools/{school}/years', [SchoolYearController::class, 'store']);
    Route::get('/schools/{school}/years/{year}', [SchoolYearController::class, 'show']);
});
