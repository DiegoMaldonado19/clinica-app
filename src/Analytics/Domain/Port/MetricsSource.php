<?php

declare(strict_types=1);

namespace App\Analytics\Domain\Port;

use App\Analytics\Domain\ReportPeriod;
use DateTimeImmutable;

/**
 * Hechos crudos de cada contexto, sin JOIN entre ellos. Las fechas llegan en la
 * zona de la clínica, para que el día de la semana sea el local.
 */
interface MetricsSource
{
    /**
     * Citas cuya hora o cuya solicitud cae en el periodo.
     *
     * @return list<array{id: string, patientId: string, source: string, status: string, startsAt: DateTimeImmutable, endsAt: DateTimeImmutable, requestedAt: DateTimeImmutable, approvedAt: ?DateTimeImmutable, reachedScheduled: bool}>
     */
    public function appointments(ReportPeriod $period, ?string $serviceId): array;

    public function pendingApprovals(?string $serviceId): int;

    /**
     * Horarios de una hora por día: los publicados menos los bloqueos, y la capacidad nominal.
     *
     * @return list<array{day: DateTimeImmutable, published: int, nominal: int}>
     */
    public function slotsPerDay(ReportPeriod $period): array;

    /**
     * Pagos aprobados en el periodo. `cash` distingue lo cobrado de un cargo cubierto o saldado con crédito.
     *
     * @return list<array{serviceId: string, service: string, amountCents: int, cash: bool, submittedAt: ?DateTimeImmutable, reviewedAt: DateTimeImmutable}>
     */
    public function approvedPayments(ReportPeriod $period): array;

    /**
     * @param  list<string>  $appointmentIds
     * @return array<string, DateTimeImmutable> cita => sello de su nota
     */
    public function noteSeals(array $appointmentIds): array;

    /** @return array<string, array{sent: int, failed: int}> canal => envíos resueltos en el periodo */
    public function deliveries(ReportPeriod $period): array;

    public function serviceName(string $serviceId): ?string;
}
