<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\RegisterAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterAccount $accounts): JsonResponse
    {
        $user = $accounts->create($request->validated());
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return UserResource::make($user)->response()->setStatusCode(201)->header('Cache-Control', 'no-store');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::guard('web')->attempt([...$request->validated(), 'status' => 'active'])) {
            throw ValidationException::withMessages(['email' => __('api.credentials_invalid')]);
        }

        $request->session()->regenerate();

        return $this->me($request);
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        return UserResource::make($request->user())->response()->header('Cache-Control', 'no-store');
    }
}
