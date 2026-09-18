<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure;

use App\Notifications\Domain\Port\NotificationChannel;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

/** Mailpit en local, SES en la nube: el mismo transporte SMTP (ADR-017). */
final class MailChannel implements NotificationChannel
{
    public function code(): string
    {
        return 'MAIL';
    }

    public function send(string $address, string $subject, string $body): void
    {
        Mail::raw($body, fn (Message $message) => $message->to($address)->subject($subject));
    }
}
