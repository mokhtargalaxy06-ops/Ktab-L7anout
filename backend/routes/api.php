<?php

use App\Http\Controllers\AuthController;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['data' => ['status' => 'ok']])->header('Cache-Control', 'no-store'));
Route::get('/v1/me', [AuthController::class, 'me'])->middleware(['auth:sanctum', EnsureActiveAccount::class]);
