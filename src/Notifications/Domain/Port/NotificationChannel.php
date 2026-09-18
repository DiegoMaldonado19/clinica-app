<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Port;

interface NotificationChannel
{
    public function code(): string;

    /** @throws \Throwable si el proveedor rechaza el envio; el trabajo reintenta */
    public function send(string $address, string $subject, string $body): void;
}
