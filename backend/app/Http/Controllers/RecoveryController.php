<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Jobs\SendAccountResetLink;
use App\Models\User;
use App\Services\AccountSecurity;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecoveryController extends Controller
{
    public function forgot(ForgotPasswordRequest $request): Response
    {
        SendAccountResetLink::dispatch($request->validated('email'));

        return response()->noContent();
    }

    public function reset(ResetPasswordRequest $request, AccountSecurity $security): Response
    {
        $user = DB::transaction(function () use ($request, $security) {
            $user = User::where('email', $request->validated('email'))->lockForUpdate()->first();
            $result = Password::reset([...$request->validated(), 'status' => 'active'], function (User $account, string $password) use ($security) {
                $account->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'session_version' => $account->session_version + 1,
                ])->save();
                $security->revokeSessions($account);
                $security->audit($account, $account, 'account.password_reset', []);
            });

            if ($result !== Password::PasswordReset) {
                throw ValidationException::withMessages(['email' => __('api.reset_invalid')]);
            }

            return $user ?? User::where('email', $request->validated('email'))->firstOrFail();
        });

        event(new PasswordReset($user));

        return response()->noContent();
    }
}
