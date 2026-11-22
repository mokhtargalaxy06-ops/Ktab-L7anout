<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Support\Access;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterAccount
{
    public function create(array $attributes): User
    {
        try {
            return DB::transaction(function () use ($attributes) {
                $code = $attributes['role'] ?? 'donor';
                abort_unless(in_array($code, Access::PUBLIC_ROLES, true), 403);
                $user = User::create(Arr::except($attributes, ['role']));
                $role = Role::where('code', $code)->firstOrFail();
                $user->roles()->attach($role->id, ['granted_at' => now()]);

                return $user->refresh();
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => __('api.email_in_use')]);
        }
    }
}
