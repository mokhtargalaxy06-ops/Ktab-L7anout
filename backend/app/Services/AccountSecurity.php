<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AccountSecurity
{
    public function changeStatus(User $actor, User $target, string $status, string $reason): User
    {
        return DB::transaction(function () use ($actor, $target, $status, $reason) {
            $account = User::lockForUpdate()->findOrFail($target->id);
            Gate::forUser($actor)->authorize('changeStatus', $account);

            $previous = $account->status;
            if ($previous === $status) {
                return $account;
            }

            abort_if($previous === 'closed', 409);
            $account->forceFill([
                'status' => $status,
                'remember_token' => Str::random(60),
                'session_version' => $account->session_version + 1,
            ])->save();
            $this->revokeSessions($account);
            Password::deleteToken($account);
            $this->audit($actor, $account, 'account.status_changed', ['from' => $previous, 'to' => $status, 'reason' => $reason]);

            return $account;
        });
    }

    public function revokeSessions(User $user): void
    {
        DB::connection(config('session.connection'))->table(config('session.table'))->where('user_id', $user->id)->delete();
        DB::table('personal_access_tokens')->where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $user->id)->delete();
    }

    public function audit(?User $actor, User $target, string $action, array $metadata): void
    {
        DB::table('audit_logs')->insert([
            'id' => (string) Str::uuid(),
            'actor_id' => $actor?->id,
            'actor_kind' => $actor ? 'user' : 'console',
            'action' => $action,
            'target_type' => 'user',
            'target_id' => $target->id,
            'correlation_id' => (string) Str::uuid(),
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
