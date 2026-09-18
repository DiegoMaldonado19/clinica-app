<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solo lectura: la tabla rechaza UPDATE y DELETE con triggers, y el unico
 * escritor es App\Shared\Infrastructure\Audit\AuditLog.
 */
class AuditLogEntry extends Model
{
    protected $table = 'audit_log';

    public $timestamps = false;

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * El paciente cuando el sujeto es un expediente; los UUID no se repiten entre
     * tablas, asi que con otro tipo de sujeto simplemente no hay fila.
     *
     * @return BelongsTo<User, $this>
     */
    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_id');
    }

    public function ipAddress(): ?string
    {
        $raw = $this->getRawOriginal('ip_address');

        return is_string($raw) && $raw !== '' ? (inet_ntop($raw) ?: null) : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'metadata' => 'array'];
    }
}
