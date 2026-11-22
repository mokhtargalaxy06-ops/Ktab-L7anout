<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountStatusRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AccountSecurity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AccountController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $request->user()->update($request->validated());

        return UserResource::make($request->user())->response()->header('Cache-Control', 'no-store');
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $input = $request->validate(['q' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'in:donor,beneficiary,shopkeeper,administrator'],
            'status' => ['nullable', 'in:active,suspended,closed']]);
        $query = User::with('roles');
        if (! empty($input['q'])) {
            $term = '%'.strtr(mb_strtolower($input['q']), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $query->where(fn ($users) => $users->whereRaw("lower(name) LIKE ? ESCAPE '!'", [$term])
                ->orWhereRaw("lower(email) LIKE ? ESCAPE '!'", [$term]));
        }
        $query->when($input['role'] ?? null, fn ($users, $role) => $users->whereHas('roles', fn ($roles) => $roles->where('code', $role)))
            ->when($input['status'] ?? null, fn ($users, $status) => $users->where('status', $status));

        return UserResource::collection($query->orderBy('id')->cursorPaginate(25))
            ->response()->header('Cache-Control', 'no-store');
    }

    public function status(AccountStatusRequest $request, User $user, AccountSecurity $security): JsonResponse
    {
        $target = $security->changeStatus($request->user(), $user, $request->validated('status'), $request->validated('reason'));

        return UserResource::make($target)->response()->header('Cache-Control', 'no-store');
    }
}
