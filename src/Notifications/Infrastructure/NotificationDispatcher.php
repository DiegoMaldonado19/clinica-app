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
 * SELECT previo: dos procesos con el mismo evento no pueden insertar ambos. Un
 * envio por cada canal activo que eligio la persona; el correo es el respaldo.
 */
final readonly class NotificationDispatcher
{
    /** Alertas que no esperan a que termine la ventana de silencio (RN-17). */
    private const URGENT = ['NT-20', 'NT-24'];

    /** @param list<NotificationChannel> $channels los activos; el primero, el correo, es el respaldo */
    public function __construct(
        private array $channels,
        private NotificationPreferences $preferences,
        private BusinessRules $rules,
        private Catalog $catalog,
        private ClockInterface $clock,
    ) {}

    public static function isUrgent(string $template): bool
    {
        return in_array($template, self::URGENT, true);
    }

    /** @param array<string, string> $data valores de los `:marcadores` de la plantilla */
    public function dispatch(string $template, string $eventKey, string $recipientId, array $data): void
    {
        $sendAt = $this->clock->now();

        if (! self::isUrgent($template)) {
            $sendAt = $this->quietHours()->nextAllowed($sendAt);
        }

        foreach ($this->channelsFor($recipientId) as $channel) {
            $key = IdempotencyKey::for("{$template}|{$eventKey}", $recipientId, $channel->code());

            $inserted = DB::table('notification_dispatches')->insertOrIgnore([
                'idempotency_key' => $key,
                'template_code' => $template,
                'channel_id' => $this->catalog->id('notification_channels', $channel->code()),
                'recipient_id' => $recipientId,
                'status_id' => $this->catalog->id('notification_statuses', 'PENDIENTE'),
                'payload' => Crypt::encryptString((string) json_encode($data, JSON_THROW_ON_ERROR)),
                'scheduled_for' => $sendAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 1) {
                SendNotificationJob::dispatch((int) DB::table('notification_dispatches')->where('idempotency_key', $key)->value('id'))
                    ->onQueue('mail')
                    ->delay($sendAt);
            }
        }
    }

    /** @return list<string> */
    public function activeChannels(): array
    {
        return array_map(fn (NotificationChannel $channel): string => $channel->code(), $this->channels);
    }

    /** Un canal que se desactivó después de programar el envío sale por el de respaldo. */
    public function channel(string $code): NotificationChannel
    {
        foreach ($this->channels as $channel) {
            if ($channel->code() === $code) {
                return $channel;
            }
        }

        return $this->channels[0];
    }

    /** @return list<NotificationChannel> lo que eligió la persona entre lo activo; si nada coincide, el correo */
    private function channelsFor(string $recipientId): array
    {
        $chosen = $this->preferences->of($recipientId);
        $channels = array_values(array_filter($this->channels, fn (NotificationChannel $channel): bool => in_array($channel->code(), $chosen, true)));

        return $channels ?: [$this->channels[0]];
    }

    public function quietHours(): QuietHoursPolicy
    {
        return QuietHoursPolicy::fromSetting(
            (string) $this->rules->value('RN-17', 'quiet_hours'),
            new DateTimeZone(config('clinic.timezone')),
        );
    }
}
