<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure;

use App\Notifications\Domain\Port\NotificationChannel;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Infrastructure\Persistence\Catalog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reintenta a 1, 5 y 25 minutos; al tercer fallo avisa a la administracion
 * con NT-20 (doc 09 §6). Cuentan las excepciones, no los intentos: una pausa
 * por la ventana de silencio no es un fallo.
 */
final class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 6;

    public int $maxExceptions = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 1500];

    public function __construct(public readonly int $dispatchId) {}

    public function handle(NotificationChannel $channel, NotificationDispatcher $dispatcher, Catalog $catalog, ClockInterface $clock): void
    {
        $dispatch = DB::table('notification_dispatches')->where('id', $this->dispatchId)->first();

        if ($dispatch === null || (int) $dispatch->status_id !== $catalog->id('notification_statuses', 'PENDIENTE')) {
            return;
        }

        // Un reintento atrasado puede caer dentro de la ventana de silencio.
        $resumeAt = $dispatcher->quietHours()->nextAllowed($clock->now());

        if ($dispatch->template_code !== 'NT-20' && $resumeAt > $clock->now()) {
            $this->release($resumeAt->getTimestamp() - $clock->now()->getTimestamp());

            return;
        }

        $template = DB::table('notification_templates')->where('code', $dispatch->template_code)->first(['subject', 'body']);
        $address = (string) DB::table('users')->where('id', $dispatch->recipient_id)->value('email');
        /** @var array<string, string> $data */
        $data = json_decode(Crypt::decryptString($dispatch->payload), true, flags: JSON_THROW_ON_ERROR);
        $replacements = array_combine(array_map(fn (string $key): string => ":{$key}", array_keys($data)), $data);

        DB::table('notification_dispatches')->where('id', $this->dispatchId)->increment('attempts');

        $channel->send($address, strtr($template->subject, $replacements), strtr($template->body, $replacements));

        DB::table('notification_dispatches')->where('id', $this->dispatchId)->update([
            'status_id' => $catalog->id('notification_statuses', 'ENVIADO'),
            'sent_at' => now(),
            'last_error' => null,
            'updated_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Notificacion no entregada', [
            'dispatch_id' => $this->dispatchId,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        $catalog = app(Catalog::class);
        $dispatch = DB::table('notification_dispatches')->where('id', $this->dispatchId)->first();

        DB::table('notification_dispatches')->where('id', $this->dispatchId)->update([
            'status_id' => $catalog->id('notification_statuses', 'FALLIDO'),
            'last_error' => mb_substr($exception->getMessage(), 0, 500),
            'updated_at' => now(),
        ]);

        // Si la que fallo es la propia alerta, no hay a quien mas avisar.
        if ($dispatch === null || $dispatch->template_code === 'NT-20') {
            return;
        }

        $recipient = (string) DB::table('users')->where('id', $dispatch->recipient_id)->value('email');

        foreach (DB::table('users')->where('role_id', DB::table('roles')->where('code', 'admin')->value('id'))->where('is_active', true)->pluck('id') as $adminId) {
            app(NotificationDispatcher::class)->dispatch('NT-20', "failed:{$this->dispatchId}", (string) $adminId, [
                'plantilla' => $dispatch->template_code,
                'destinatario' => $recipient,
                'error' => mb_substr($exception->getMessage(), 0, 200),
            ], urgent: true);
        }
    }
}
