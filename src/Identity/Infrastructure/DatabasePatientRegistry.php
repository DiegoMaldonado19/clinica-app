<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use App\Identity\Domain\Event\PatientAccountCreated;
use App\Scheduling\Domain\Port\PatientRegistry;
use App\Shared\Domain\BusinessRules;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\Event\EventBus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Alta automatica del paciente al agendar (RF-06). Vive en Identity porque la
 * cuenta es de Identity; Scheduling solo ve el puerto.
 */
final readonly class DatabasePatientRegistry implements PatientRegistry
{
    public function __construct(
        private BusinessRules $rules,
        private EventBus $events,
        private ClockInterface $clock,
    ) {}

    public function findOrRegister(string $name, string $email, string $phoneE164, string $nit): string
    {
        $email = Str::lower(trim($email));
        $existing = DB::table('users')->where('email', $email)->value('id');

        if ($existing !== null) {
            DB::table('patients')->insertOrIgnore(['id' => $existing, 'nit' => $nit, 'created_at' => now(), 'updated_at' => now()]);

            return (string) $existing;
        }

        $id = (string) Str::uuid7();
        $password = Str::password(12, symbols: false);
        $ttlHours = (int) $this->rules->value('RN-15', 'temp_password_ttl_hours');

        DB::table('users')->insert([
            'id' => $id,
            'role_id' => DB::table('roles')->where('code', 'patient')->value('id'),
            'name' => $name,
            'email' => $email,
            'phone_e164' => $phoneE164,
            'password' => Hash::make($password),
            'must_change_password' => true,
            'temp_password_expires_at' => now()->addHours($ttlHours),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('patients')->insert(['id' => $id, 'nit' => $nit, 'created_at' => now(), 'updated_at' => now()]);

        $this->events->publish(new PatientAccountCreated($id, $this->clock->now(), $password, $ttlHours));

        return $id;
    }

    public function recordConsents(string $patientId, array $versions, ?string $ipAddress): void
    {
        $packedIp = $ipAddress !== null ? inet_pton($ipAddress) : false;

        DB::table('consents')->insert(array_map(fn (string $code, string $version): array => [
            'id' => (string) Str::uuid7(),
            'patient_id' => $patientId,
            'document_code' => $code,
            'document_version' => $version,
            'accepted_at' => now(),
            'ip_address' => $packedIp === false ? null : $packedIp,
        ], array_keys($versions), $versions));
    }
}
