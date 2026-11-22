<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('mail.default') === 'log') {
            config(['mail.default' => 'smtp']);
        }

        ResetPassword::createUrlUsing(fn (User $user, string $token) => rtrim(config('app.frontend_url'), '/').'/reset-password#'.http_build_query([
            'token' => $token, 'email' => $user->email,
        ]));
        VerifyEmail::createUrlUsing(fn (User $user) => rtrim(config('app.url'), '/').URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
            absolute: false,
        ));

        RateLimiter::for('recovery', fn (Request $request) => Limit::perMinute(8)->by($request->ip()));
        RateLimiter::for('account-status', fn (Request $request) => Limit::perMinute(10)->by($request->user()->id));
        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email');
            $normalized = is_string($email) ? Str::lower(trim($email)) : '';

            return [
                Limit::perMinute(30)->by('ip:'.$request->ip()),
                Limit::perMinute(10)->by('account:'.hash('sha256', $normalized)),
            ];
        });

        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
