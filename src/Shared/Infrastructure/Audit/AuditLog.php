<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Audit;

use Illuminate\Support\Facades\DB;

/**
 * Bitacora de solo insercion (doc 05 §5.1, RN-12). No tiene metodo para editar
 * ni borrar, y la base lo impide aunque alguien lo intente.
 */
final class AuditLog
{
    /** @param array<string, mixed> $metadata */
    public static function record(string $action, ?string $subjectType = null, ?string $subjectId = null, array $metadata = [], ?string $actorId = null): void
    {
        $request = request();
        $ip = $request->ip() !== null ? inet_pton($request->ip()) : false;

        DB::table('audit_log')->insert([
            'actor_user_id' => $actorId ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'ip_address' => $ip === false ? null : $ip,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'occurred_at' => now()->format('Y-m-d H:i:s.v'),
        ]);
    }
}
