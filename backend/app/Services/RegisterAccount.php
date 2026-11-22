<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterAccount
{
    public function create(array $attributes): User
    {
        try {
            return DB::transaction(function () use ($attributes) {
                $user = User::create($attributes);
                $role = Role::where('code', 'donor')->firstOrFail();
                $user->roles()->attach($role->id, ['granted_at' => now()]);

                return $user->refresh();
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => __('api.email_in_use')]);
        }
    }
}
