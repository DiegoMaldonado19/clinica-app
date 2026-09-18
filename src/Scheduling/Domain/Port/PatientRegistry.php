<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Port;

interface PatientRegistry
{
    /**
     * HU-03: si el correo ya existe devuelve ese paciente, sin credenciales
     * nuevas; si no, lo registra con credencial temporal (RF-06, RN-15).
     */
    public function findOrRegister(string $name, string $email, string $phoneE164, string $nit): string;

    /** @param array<string, string> $versions codigo de documento => version aceptada */
    public function recordConsents(string $patientId, array $versions, ?string $ipAddress): void;
}
