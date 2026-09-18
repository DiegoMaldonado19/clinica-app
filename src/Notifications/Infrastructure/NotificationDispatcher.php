<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure;

use App\Notifications\Domain\IdempotencyKey;
use App\Notifications\Domain\Port\NotificationChannel;
use App\Notifications\Domain\QuietHoursPolicy;
use App\Shared\Domain\BusinessRules;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Infrastructure\Persistence\Catalog;
use DateTimeZone;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Entrada unica del motor. La idempotencia la garantiza el indice unico, no un
 * SELECT previo: dos procesos con el mismo evento no pueden insertar ambos.
 */
final readonly class NotificationDispatcher
{
    public function __construct(
        private NotificationChannel $channel,
        private BusinessRules $rules,
        private Catalog $catalog,
        private ClockInterface $clock,
    ) {}

    /**
     * @param  array<string, string>  $data  valores de los `:marcadores` de la plantilla
     * @param  bool  $urgent  NT-24 y similares ignoran la ventana de silencio
     */
    public function dispatch(
        string $template,
        string $eventKey,
        string $recipientId,
        array $data,
        bool $urgent = false,
    ): void {
        $sendAt = $this->clock->now();

        if (! $urgent) {
            $sendAt = $this->quietHours()->nextAllowed($sendAt);
        }

        $key = IdempotencyKey::for("{$template}|{$eventKey}", $recipientId, $this->channel->code());

        $inserted = DB::table('notification_dispatches')->insertOrIgnore([
            'idempotency_key' => $key,
            'template_code' => $template,
            'channel_id' => $this->catalog->id('notification_channels', $this->channel->code()),
            'recipient_id' => $recipientId,
            'status_id' => $this->catalog->id('notification_statuses', 'PENDIENTE'),
            'payload' => Crypt::encryptString((string) json_encode($data, JSON_THROW_ON_ERROR)),
            'scheduled_for' => $sendAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0) {
            return;
        }

        SendNotificationJob::dispatch((int) DB::table('notification_dispatches')->where('idempotency_key', $key)->value('id'))
            ->onQueue('mail')
            ->delay($sendAt);
    }

    public function quietHours(): QuietHoursPolicy
    {
        return QuietHoursPolicy::fromSetting(
            (string) $this->rules->value('RN-17', 'quiet_hours'),
            new DateTimeZone(config('clinic.timezone')),
        );
    }
}
