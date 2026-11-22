<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class VerificationController extends Controller
{
    public function resend(Request $request): Response
    {
        if (! $request->user()->hasVerifiedEmail()) {
            try {
                $request->user()->sendEmailVerificationNotification();
            } catch (TransportExceptionInterface) {
                abort(503);
            }
        }

        return response()->noContent();
    }

    public function verify(string $id, string $hash): RedirectResponse
    {
        $user = User::whereKey($id)->where('status', 'active')->firstOrFail();
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->away(rtrim(config('app.frontend_url'), '/').'/email-verified')
            ->withHeaders(['Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store']);
    }
}
