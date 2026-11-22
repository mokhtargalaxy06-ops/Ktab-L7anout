<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Password;

class SendAccountResetLink implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 65;

    public function __construct(private readonly string $email) {}

    public function handle(): void
    {
        Password::sendResetLink(['email' => $this->email, 'status' => 'active']);
    }
}
