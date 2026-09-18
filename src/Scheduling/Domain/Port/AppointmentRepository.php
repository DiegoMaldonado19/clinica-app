<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Port;

use App\Scheduling\Domain\Exception\SlotUnavailable;
use App\Scheduling\Domain\Model\Appointment;
use DateTimeImmutable;

interface AppointmentRepository
{
    public function nextId(): string;

    public function find(string $id): ?Appointment;

    /** @throws SlotUnavailable si el horario ya esta tomado */
    public function save(Appointment $appointment): void;

    /** @return list<string> */
    public function idsWithExpiredHolds(DateTimeImmutable $now): array;

    /** @return list<string> */
    public function idsWithOverduePayments(DateTimeImmutable $now): array;
}
