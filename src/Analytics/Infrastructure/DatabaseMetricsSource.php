<?php

declare(strict_types=1);

namespace App\Analytics\Infrastructure;

use App\Analytics\Domain\Port\MetricsSource;
use App\Analytics\Domain\ReportPeriod;
use App\Scheduling\Domain\AvailabilityCalculator;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use App\Shared\Infrastructure\Persistence\Catalog;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Una consulta por contexto. Lo que cruza contextos se une en PHP por el id de
 * la cita, nunca con JOIN (CLAUDE.md).
 */
final readonly class DatabaseMetricsSource implements MetricsSource
{
    private const SLOT_MINUTES = 60;

    public function __construct(private Catalog $catalog) {}

    public function appointments(ReportPeriod $period, ?string $serviceId): array
    {
        [$from, $to] = $this->utc($period);

        return DB::table('appointments')
            ->join('appointment_statuses as s', 's.id', '=', 'appointments.status_id')
            ->when($serviceId, fn (Builder $query) => $query->where('appointments.service_id', $serviceId))
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $q) => $q->where('starts_at', '>=', $from)->where('starts_at', '<', $to))
                ->orWhere(fn (Builder $q) => $q->where('requested_at', '>=', $from)->where('requested_at', '<', $to)))
            ->select(['appointments.id', 'patient_id', 'source', 's.code as status', 'starts_at', 'ends_at', 'requested_at', 'approved_at'])
            ->selectRaw(
                'exists (select 1 from appointment_status_log l where l.appointment_id = appointments.id and l.to_status_id = ?) as reached_scheduled',
                [$this->catalog->id('appointment_statuses', 'AGENDADA')],
            )
            ->get()
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'patientId' => (string) $row->patient_id,
                'source' => (string) $row->source,
                'status' => (string) $row->status,
                'startsAt' => $this->local($row->starts_at),
                'endsAt' => $this->local($row->ends_at),
                'requestedAt' => $this->local($row->requested_at),
                'approvedAt' => $row->approved_at !== null ? $this->local($row->approved_at) : null,
                'reachedScheduled' => (bool) $row->reached_scheduled,
            ])
            ->values()
            ->all();
    }

    public function pendingApprovals(?string $serviceId): int
    {
        return DB::table('appointments')
            ->where('status_id', $this->catalog->id('appointment_statuses', 'SOLICITADA'))
            ->when($serviceId, fn (Builder $query) => $query->where('service_id', $serviceId))
            ->count();
    }

    public function slotsPerDay(ReportPeriod $period): array
    {
        [$from, $to] = $this->utc($period);

        $rules = DB::table('availability_rules')->get(['weekday', 'starts_at', 'ends_at'])
            ->map(fn (object $rule): array => [
                'weekday' => (int) $rule->weekday,
                'starts' => substr((string) $rule->starts_at, 0, 5),
                'ends' => substr((string) $rule->ends_at, 0, 5),
            ])
            ->values()
            ->all();

        $blocks = DB::table('schedule_blocks')->where('starts_at', '<', $to)->where('ends_at', '>', $from)->get(['starts_at', 'ends_at'])
            ->map(fn (object $block): TimeSlot => new TimeSlot($this->local($block->starts_at), $this->local($block->ends_at)))
            ->values()
            ->all();

        $days = [];

        for ($day = $period->from->setTimezone($this->timezone())->setTime(0, 0); $day < $period->to; $day = $day->modify('+1 day')) {
            $days[] = [
                'day' => $day,
                'published' => count(AvailabilityCalculator::freeSlots($day, $rules, $blocks, self::SLOT_MINUTES)),
                'nominal' => count(AvailabilityCalculator::freeSlots($day, $rules, [], self::SLOT_MINUTES)),
            ];
        }

        return $days;
    }

    public function approvedPayments(ReportPeriod $period): array
    {
        [$from, $to] = $this->utc($period);

        $payments = DB::table('payments')
            ->where('status_id', $this->catalog->id('payment_statuses', 'APROBADO'))
            ->where('reviewed_at', '>=', $from)
            ->where('reviewed_at', '<', $to)
            ->get(['appointment_id', 'amount_cents', 'method_id', 'submitted_at', 'reviewed_at']);

        $services = DB::table('appointments')
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->whereIn('appointments.id', $payments->pluck('appointment_id')->unique()->all())
            ->get(['appointments.id', 'services.id as service_id', 'services.name'])
            ->keyBy('id');

        return $payments->map(fn (object $payment): array => [
            'serviceId' => (string) $services[$payment->appointment_id]->service_id,
            'service' => (string) $services[$payment->appointment_id]->name,
            'amountCents' => (int) $payment->amount_cents,
            'cash' => $payment->method_id !== null,
            'submittedAt' => $payment->submitted_at !== null ? $this->local($payment->submitted_at) : null,
            'reviewedAt' => $this->local($payment->reviewed_at),
        ])->values()->all();
    }

    public function noteSeals(array $appointmentIds): array
    {
        return DB::table('clinical_notes')
            ->where('version', 1)
            ->whereNotNull('sealed_at')
            ->whereIn('appointment_id', $appointmentIds)
            ->pluck('sealed_at', 'appointment_id')
            ->map(fn (string $sealedAt): DateTimeImmutable => $this->local($sealedAt))
            ->all();
    }

    public function deliveries(ReportPeriod $period): array
    {
        [$from, $to] = $this->utc($period);
        $failed = $this->catalog->id('notification_statuses', 'FALLIDO');

        $rows = DB::table('notification_dispatches')
            ->join('notification_channels as c', 'c.id', '=', 'notification_dispatches.channel_id')
            ->where('notification_dispatches.created_at', '>=', $from)
            ->where('notification_dispatches.created_at', '<', $to)
            ->whereIn('status_id', [
                $this->catalog->id('notification_statuses', 'ENVIADO'),
                $this->catalog->id('notification_statuses', 'ENTREGADO'),
                $failed,
            ])
            ->groupBy('c.label', 'status_id')
            ->selectRaw('c.label as channel, status_id, count(*) as total')
            ->get();

        $deliveries = [];

        foreach ($rows as $row) {
            $deliveries[$row->channel] ??= ['sent' => 0, 'failed' => 0];
            $deliveries[$row->channel][(int) $row->status_id === $failed ? 'failed' : 'sent'] += (int) $row->total;
        }

        return $deliveries;
    }

    public function serviceName(string $serviceId): ?string
    {
        $name = DB::table('services')->where('id', $serviceId)->value('name');

        return $name !== null ? (string) $name : null;
    }

    /** @return array{0: string, 1: string} */
    private function utc(ReportPeriod $period): array
    {
        $utc = new DateTimeZone('UTC');

        return [$period->from->setTimezone($utc)->format('Y-m-d H:i:s'), $period->to->setTimezone($utc)->format('Y-m-d H:i:s')];
    }

    private function local(string $utc): DateTimeImmutable
    {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone($this->timezone());
    }

    private function timezone(): DateTimeZone
    {
        return new DateTimeZone(config('clinic.timezone'));
    }
}
