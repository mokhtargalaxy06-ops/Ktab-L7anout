<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $hasSession = $request->hasSession();
        $inactive = $user->status !== 'active';
        if ($inactive || ! $hasSession || $request->session()->get('account_version') !== $user->session_version) {
            if ($hasSession) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            abort($inactive ? 403 : 401);
        }

        return $next($request);
    }
}
