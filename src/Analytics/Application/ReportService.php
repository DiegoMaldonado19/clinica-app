<?php

declare(strict_types=1);

namespace App\Analytics\Application;

use App\Analytics\Domain\Kpi;
use App\Analytics\Domain\Port\MetricsSource;
use App\Analytics\Domain\Report;
use App\Analytics\Domain\ReportPeriod;
use App\Shared\Domain\Clock\ClockInterface;
use DateTimeImmutable;

/**
 * Los 13 indicadores del doc 05 de la Fase 1, calculados al vuelo. Lo que mide
 * sesiones solo cuenta citas cuya hora ya pasó; una cita futura todavía no pudo
 * ser una inasistencia.
 */
final readonly class ReportService
{
    private const ONLINE = ['web', 'chat'];

    private const ATTENDED = ['EN_CURSO', 'ATENDIDA'];

    private const RELEASED = ['RECHAZADA', 'EXPIRADA', 'CANCELADA_IMPAGO', 'CANCELADA_SIN_CARGO', 'CANCELADA_CON_RECARGO'];

    public function __construct(
        private MetricsSource $source,
        private ClockInterface $clock,
    ) {}

    public function build(ReportPeriod $period, ?string $serviceId, bool $financial): Report
    {
        $now = $this->clock->now();
        $all = $this->source->appointments($period, $serviceId);

        $sessions = array_filter($all, fn (array $a): bool => $period->contains($a['startsAt']));
        $occupying = array_filter($sessions, fn (array $a): bool => ! in_array($a['status'], self::RELEASED, true));
        $past = array_filter($sessions, fn (array $a): bool => $a['startsAt'] < $now);
        $scheduled = array_filter($past, fn (array $a): bool => $a['reachedScheduled']);
        $confirmed = array_filter($past, fn (array $a): bool => $a['approvedAt'] !== null);
        $attended = array_filter($past, fn (array $a): bool => in_array($a['status'], self::ATTENDED, true));
        $requested = array_filter($all, fn (array $a): bool => $period->contains($a['requestedAt']));
        $online = array_filter($requested, fn (array $a): bool => in_array($a['source'], self::ONLINE, true));

        $payments = array_filter(
            $this->source->approvedPayments($period),
            fn (array $p): bool => $serviceId === null || $p['serviceId'] === $serviceId,
        );
        $cash = array_filter($payments, fn (array $p): bool => $p['cash']);

        $slots = $this->source->slotsPerDay($period);
        $elapsed = array_filter($slots, fn (array $s): bool => $s['day'] < $now);
        $deliveries = $this->source->deliveries($period);

        $indicators = [
            new Kpi('', 'Sesiones atendidas', count($attended)),
            new Kpi('', 'Pacientes atendidos', count(array_unique(array_column($attended, 'patientId')))),
            new Kpi('', 'Solicitudes por aprobar', $this->source->pendingApprovals($serviceId), note: 'Al momento de consultar'),
            new Kpi('KPI-01', 'Tasa de inasistencia', self::rate(self::countStatus($scheduled, 'NO_SHOW'), count($scheduled)), '%', 'Meta ≤ 8 %'),
            new Kpi('KPI-02', 'Tiempo medio de aprobación', self::averageHours(array_map(
                fn (array $a): array => [$a['requestedAt'], $a['approvedAt']],
                array_filter($online, fn (array $a): bool => $a['approvedAt'] !== null),
            )), 'h', 'Meta ≤ 6 h', 'Solicitudes en línea'),
            new Kpi('KPI-03', 'Solicitudes expiradas', self::rate(self::countStatus($online, 'EXPIRADA'), count($online)), '%', 'Meta ≤ 5 %'),
            new Kpi('KPI-04', 'Conversión de la landing', null, '%', 'Meta: línea base + 20 %', 'Sin medición de visitantes'),
            new Kpi('KPI-05', 'Tiempo medio de conciliación', self::averageHours(array_map(
                fn (array $p): array => [$p['submittedAt'], $p['reviewedAt']],
                array_filter($payments, fn (array $p): bool => $p['submittedAt'] !== null),
            )), 'h', 'Meta ≤ 4 h'),
            new Kpi('KPI-06', 'Ocupación de agenda', self::rate(count($occupying), array_sum(array_column($slots, 'published'))), '%', 'Meta ≥ 70 %', 'Sobre las horas publicadas, sin los bloqueos'),
            ...($financial ? [new Kpi('KPI-07', 'Ingreso cobrado', array_sum(array_column($cash, 'amountCents')), 'Q', note: 'Pagos aprobados por transferencia o en efectivo')] : []),
            new Kpi('KPI-08', 'Cancelación tardía', self::rate(self::countStatus($confirmed, 'CANCELADA_CON_RECARGO'), count($confirmed)), '%', 'Meta ≤ 10 %'),
            new Kpi('KPI-09', 'Adherencia al tratamiento', self::rate(count($attended), count($scheduled)), '%', 'Meta ≥ 75 %'),
            new Kpi('KPI-10', 'Entrega de notificaciones', self::rate(
                array_sum(array_column($deliveries, 'sent')),
                array_sum(array_column($deliveries, 'sent')) + array_sum(array_column($deliveries, 'failed')),
            ), '%', 'Meta ≥ 97 %', self::perChannel($deliveries)),
            new Kpi('KPI-11', 'Citas sin intervención humana', self::rate(count($online), count($requested)), '%', 'Meta ≥ 70 %'),
            new Kpi('KPI-12', 'Notas selladas en 24 h', $this->sealedOnTime($attended, $now), '%', 'Meta ≥ 95 %'),
            new Kpi('KPI-13', 'Ocupación sobre capacidad publicada', self::rate(count($attended), array_sum(array_column($elapsed, 'nominal'))), '%', 'Meta ≥ 60 %'),
        ];

        return new Report(
            $period,
            $serviceId !== null ? $this->source->serviceName($serviceId) : null,
            $indicators,
            $financial ? self::revenueByService($cash) : [],
            self::occupancyByWeekday($slots, $occupying),
        );
    }

    /**
     * Una sesión cuenta cuando su nota se selló o cuando ya vencieron las 24 h para hacerlo.
     *
     * @param  array<array{id: string, endsAt: DateTimeImmutable}>  $attended
     */
    private function sealedOnTime(array $attended, DateTimeImmutable $now): ?float
    {
        $seals = $this->source->noteSeals(array_column($attended, 'id'));
        $due = $onTime = 0;

        foreach ($attended as $session) {
            $deadline = $session['endsAt']->modify('+24 hours');
            $sealedAt = $seals[$session['id']] ?? null;

            if ($sealedAt !== null || $deadline < $now) {
                $due++;
                $onTime += (int) ($sealedAt !== null && $sealedAt <= $deadline);
            }
        }

        return self::rate($onTime, $due);
    }

    /**
     * @param  array<array{service: string, amountCents: int}>  $cash
     * @return array<string, int>
     */
    private static function revenueByService(array $cash): array
    {
        $revenue = [];

        foreach ($cash as $payment) {
            $revenue[$payment['service']] = ($revenue[$payment['service']] ?? 0) + $payment['amountCents'];
        }

        arsort($revenue);

        return $revenue;
    }

    /**
     * @param  list<array{day: DateTimeImmutable, published: int, nominal: int}>  $slots
     * @param  array<array{startsAt: DateTimeImmutable}>  $occupying
     * @return array<int, array{published: int, occupied: int, rate: float}>
     */
    private static function occupancyByWeekday(array $slots, array $occupying): array
    {
        $published = $occupied = [];

        foreach ($slots as $slot) {
            $weekday = (int) $slot['day']->format('N');
            $published[$weekday] = ($published[$weekday] ?? 0) + $slot['published'];
        }

        foreach ($occupying as $session) {
            $weekday = (int) $session['startsAt']->format('N');
            $occupied[$weekday] = ($occupied[$weekday] ?? 0) + 1;
        }

        $weekdays = [];

        foreach (array_filter($published) as $weekday => $hours) {
            $taken = $occupied[$weekday] ?? 0;
            $weekdays[$weekday] = ['published' => $hours, 'occupied' => $taken, 'rate' => (float) self::rate($taken, $hours)];
        }

        ksort($weekdays);

        return $weekdays;
    }

    /** @param array<string, array{sent: int, failed: int}> $deliveries */
    private static function perChannel(array $deliveries): string
    {
        $parts = [];

        foreach ($deliveries as $channel => $counts) {
            $parts[] = "{$channel}: ".(self::rate($counts['sent'], $counts['sent'] + $counts['failed']) ?? '—').' %';
        }

        return implode(' · ', $parts);
    }

    /** @param array<array{status: string}> $appointments */
    private static function countStatus(array $appointments, string $status): int
    {
        return count(array_filter($appointments, fn (array $a): bool => $a['status'] === $status));
    }

    /** @param array<array{0: DateTimeImmutable, 1: DateTimeImmutable}> $intervals */
    private static function averageHours(array $intervals): ?float
    {
        if ($intervals === []) {
            return null;
        }

        $seconds = array_sum(array_map(fn (array $i): int => $i[1]->getTimestamp() - $i[0]->getTimestamp(), $intervals));

        return round($seconds / count($intervals) / 3600, 1);
    }

    private static function rate(int $part, int $whole): ?float
    {
        return $whole === 0 ? null : round($part * 100 / $whole, 1);
    }
}
