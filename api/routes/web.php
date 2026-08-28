<?php

use App\Http\Controllers\SpaController;
use App\Http\Controllers\VerifyCertificatePageController;
use Illuminate\Support\Facades\Route;

Route::get('/verify/{token}', VerifyCertificatePageController::class)
    ->middleware('throttle:certificate-verify')
    ->where('token', '[0-9a-f]{40}');

Route::get('/{path?}', SpaController::class)
    ->where('path', '^(?!api|up|storage|verify).*$');
