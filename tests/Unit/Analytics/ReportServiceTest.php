<?php

declare(strict_types=1);

use App\Analytics\Application\ReportService;
use App\Analytics\Domain\Kpi;
use App\Analytics\Domain\Port\MetricsSource;
use App\Analytics\Domain\ReportPeriod;
use App\Shared\Infrastructure\Clock\FrozenClock;

/** Las fórmulas del doc 05 con hechos en memoria: sin base de datos. */
function reportWith(array $appointments, array $seals = [], bool $financial = true): array
{
    $source = new class($appointments, $seals) implements MetricsSource
    {
        public function __construct(private array $appointments, private array $seals) {}

        public function appointments(ReportPeriod $period, ?string $serviceId): array
        {
            return $this->appointments;
        }

        public function pendingApprovals(?string $serviceId): int
        {
            return 0;
        }

        public function slotsPerDay(ReportPeriod $period): array
        {
            return [];
        }

        public function approvedPayments(ReportPeriod $period): array
        {
            return [];
        }

        public function noteSeals(array $appointmentIds): array
        {
            return $this->seals;
        }

        public function deliveries(ReportPeriod $period): array
        {
            return [];
        }

        public function serviceName(string $serviceId): ?string
        {
            return null;
        }
    };

    $report = (new ReportService($source, new FrozenClock(new DateTimeImmutable('2026-10-20 12:00'))))
        ->build(new ReportPeriod(new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-11-01')), null, $financial);

    return array_column(array_map(fn (Kpi $kpi): array => [$kpi->code ?: $kpi->label, $kpi->value], $report->indicators), 1, 0);
}

function appointmentFact(string $id, string $status, string $startsAt, array $overrides = []): array
{
    return $overrides + [
        'id' => $id, 'patientId' => "p-{$id}", 'source' => 'web', 'status' => $status,
        'startsAt' => new DateTimeImmutable($startsAt), 'endsAt' => new DateTimeImmutable("{$startsAt} +50 minutes"),
        'requestedAt' => new DateTimeImmutable('2026-10-01 08:00'), 'approvedAt' => new DateTimeImmutable('2026-10-01 12:00'),
        'reachedScheduled' => true,
    ];
}

it('mide la inasistencia solo sobre las citas agendadas cuya hora ya paso (KPI-01)', function () {
    $kpis = reportWith([
        appointmentFact('a', 'NO_SHOW', '2026-10-05 09:00'),
        appointmentFact('b', 'ATENDIDA', '2026-10-06 09:00'),
        appointmentFact('c', 'EXPIRADA', '2026-10-07 09:00', ['reachedScheduled' => false, 'approvedAt' => null]),
        appointmentFact('d', 'AGENDADA', '2026-10-28 09:00'),
    ]);

    expect($kpis['KPI-01'])->toBe(50.0)
        ->and($kpis['KPI-09'])->toBe(50.0)
        ->and($kpis['Sesiones atendidas'])->toBe(1);
});

it('toma el tiempo de aprobacion solo de las solicitudes en linea (KPI-02)', function () {
    $kpis = reportWith([
        appointmentFact('web', 'AGENDADA', '2026-10-05 09:00'),
        appointmentFact('phone', 'AGENDADA', '2026-10-05 10:00', ['source' => 'phone', 'approvedAt' => new DateTimeImmutable('2026-10-01 08:00')]),
    ]);

    expect($kpis['KPI-02'])->toBe(4.0)
        ->and($kpis['KPI-11'])->toBe(50.0);
});

it('cuenta una nota solo cuando se sello o ya vencio su plazo de 24 h (KPI-12)', function () {
    $kpis = reportWith([
        appointmentFact('on-time', 'ATENDIDA', '2026-10-05 09:00'),
        appointmentFact('late', 'ATENDIDA', '2026-10-06 09:00'),
        appointmentFact('pending', 'EN_CURSO', '2026-10-20 09:00'),
    ], [
        'on-time' => new DateTimeImmutable('2026-10-05 18:00'),
        'late' => new DateTimeImmutable('2026-10-08 09:00'),
    ]);

    expect($kpis['KPI-12'])->toBe(50.0);
});

it('declara sin dato lo que no tiene denominador y omite el ingreso sin permiso financiero', function () {
    $kpis = reportWith([], financial: false);

    expect($kpis['KPI-01'])->toBeNull()
        ->and($kpis['KPI-04'])->toBeNull()
        ->and($kpis)->not->toHaveKey('KPI-07');
});
