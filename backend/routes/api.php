<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\VerificationController;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['data' => ['status' => 'ok']])->header('Cache-Control', 'no-store'));
Route::prefix('v1')->middleware(['auth:sanctum', EnsureActiveAccount::class])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me', [AccountController::class, 'update']);
    Route::post('/email/verification-notification', [VerificationController::class, 'resend'])->middleware('throttle:3,1');
    Route::get('/admin/users', [AccountController::class, 'index']);
    Route::patch('/admin/users/{user}/status', [AccountController::class, 'status'])->middleware('throttle:account-status');
});
