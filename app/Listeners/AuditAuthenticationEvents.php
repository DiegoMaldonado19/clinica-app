<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Shared\Infrastructure\Audit\AuditLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/** Doc 05 §5.1: `login.succeeded` y `login.failed`. El correo intentado no se guarda. */
final class AuditAuthenticationEvents
{
    public function succeeded(Login $event): void
    {
        $userId = (string) $event->user->getAuthIdentifier();

        DB::table('users')->where('id', $userId)->update(['last_login_at' => now()]);
        AuditLog::record('login.succeeded', 'user', $userId, actorId: $userId);
    }

    public function failed(Failed $event): void
    {
        $userId = $event->user?->getAuthIdentifier();

        AuditLog::record('login.failed', 'user', $userId !== null ? (string) $userId : null, ['guard' => $event->guard]);
    }

    /** @return array<class-string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'succeeded',
            Failed::class => 'failed',
        ];
    }
}
