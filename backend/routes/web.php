<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RecoveryController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:web');
Route::post('/forgot-password', [RecoveryController::class, 'forgot'])->middleware('throttle:recovery');
Route::post('/reset-password', [RecoveryController::class, 'reset'])->middleware('throttle:recovery');
Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
    ->middleware(['signed:relative', 'throttle:20,1'])->whereUuid('id')->name('verification.verify');
