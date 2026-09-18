<?php

declare(strict_types=1);

namespace App\ClinicalRecords\Domain\Port;

use App\ClinicalRecords\Domain\ClinicalNote;

interface ClinicalNoteRepository
{
    public function nextId(): string;

    public function find(string $id): ?ClinicalNote;

    /** El borrador o la ultima version de la nota de esa cita. */
    public function latestForAppointment(string $appointmentId): ?ClinicalNote;

    /** Abre el expediente si el paciente aun no tiene uno. */
    public function recordIdFor(string $patientId): string;

    public function save(ClinicalNote $note): void;
}
