<?php

declare(strict_types=1);

namespace App\Notifications\Domain;

use App\Shared\Domain\Event\DomainEvent;

/** RN-17: un mismo evento, destinatario y canal producen un solo envio. */
final class IdempotencyKey
{
    public static function for(string $eventKey, string $recipientId, string $channel): string
    {
        return hash('sha256', "{$eventKey}|{$recipientId}|{$channel}");
    }

    /** Identidad de un evento: el mismo objeto reprocesado da la misma clave. */
    public static function eventKey(DomainEvent $event): string
    {
        return $event::class.'|'.$event->aggregateId().'|'.$event->occurredAt()->format('U.u');
    }
}
