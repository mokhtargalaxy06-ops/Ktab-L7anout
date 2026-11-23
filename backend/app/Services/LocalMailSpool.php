<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class LocalMailSpool extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        if (! app()->environment('local')) {
            throw new TransportException('The local mail spool requires the local environment.');
        }

        if (! Storage::disk('local')->put('mail/'.Str::uuid7().'.eml', $message->toString(), 'private')) {
            throw new TransportException('The local mailbox could not store the message.');
        }
    }

    public function __toString(): string
    {
        return 'local-spool';
    }
}
