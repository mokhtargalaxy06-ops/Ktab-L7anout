<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountStatusRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AccountSecurity;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AccountController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $request->user()->update($request->validated());

        return UserResource::make($request->user())->response()->header('Cache-Control', 'no-store');
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        return UserResource::collection(User::with('roles')->orderBy('id')->cursorPaginate(25))
            ->response()->header('Cache-Control', 'no-store');
    }

    public function status(AccountStatusRequest $request, User $user, AccountSecurity $security): JsonResponse
    {
        $target = $security->changeStatus($request->user(), $user, $request->validated('status'));

        return UserResource::make($target)->response()->header('Cache-Control', 'no-store');
    }
}
