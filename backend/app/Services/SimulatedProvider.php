<?php

namespace App\Services;

use Illuminate\Support\Str;

class SimulatedProvider
{
    public static function enabled(): bool
    {
        return app()->environment(['local', 'testing', 'staging']) && config('funding.simulator_enabled') === true;
    }

    public function event(object $payment, string $outcome): array
    {
        return [
            'event_id' => (string) Str::uuid(),
            'reference' => $payment->provider_reference,
            'amount_minor' => (string) $payment->amount_minor,
            'currency' => 'MAD',
            'outcome' => $outcome,
        ];
    }

    public function authenticated(string $body, string $timestamp, string $signature): bool
    {
        $secret = config('funding.simulator_secret');
        if (! self::enabled() || ! is_string($secret) || strlen($secret) < 32 || ! preg_match('/^[0-9]{1,11}$/D', $timestamp)
            || abs(now()->timestamp - (int) $timestamp) > 300) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.'.'.$body, $secret), $signature);
    }
}
