<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Billing\Domain\Event\LateCancellationFeeApplied;
use App\Billing\Domain\Event\PatientCreditIssued;
use App\Billing\Domain\Event\PaymentProofSubmitted;
use App\Billing\Domain\Event\PaymentRejected;
use App\ClinicalRecords\Domain\Event\ClinicalNoteSealed;
use App\Identity\Domain\Event\PatientAccountCreated;
use App\Notifications\Domain\IdempotencyKey;
use App\Notifications\Infrastructure\NotificationDispatcher;
use App\Scheduling\Domain\Event\AppointmentApproved;
use App\Scheduling\Domain\Event\AppointmentCancelled;
use App\Scheduling\Domain\Event\AppointmentCancelledUnpaid;
use App\Scheduling\Domain\Event\AppointmentFullyBooked;
use App\Scheduling\Domain\Event\AppointmentRejected;
use App\Scheduling\Domain\Event\PreAppointmentExpired;
use App\Scheduling\Domain\Event\PreAppointmentRequested;
use App\Scheduling\Domain\Event\ReminderDue;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Money;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El catalogo NT del doc 10 §7 hecho codigo: que evento avisa a quien y con que
 * plantilla. Los eventos sin fila aqui no tienen notificacion en la Fase 2.
 */
final readonly class DomainEventNotifier
{
    public function __construct(private NotificationDispatcher $notifications) {}

    public function accountCreated(PatientAccountCreated $event): void
    {
        $user = DB::table('users')->where('id', $event->patientId)->first(['name', 'email']);

        $this->notify('NT-02', $event, $event->patientId, [
            'nombre' => $user->name,
            'correo' => $user->email,
            'contrasena' => $event->temporaryPassword,
            'horas' => (string) $event->passwordTtlHours,
            'portal' => url('/portal'),
        ]);
    }

    public function requested(PreAppointmentRequested $event): void
    {
        $data = $this->summary($event->appointmentId) + ['vence' => $this->local($event->holdExpiresAt->format('Y-m-d H:i:s'))];

        $this->notify('NT-01', $event, $event->patientId, $data);
        $this->notifyStaff('NT-03', $event, $data + ['bandeja' => url('/admin/approval-inbox')]);
    }

    public function approved(AppointmentApproved $event): void
    {
        // Agendada por recepcion con el plazo ya vencido: se cobra en caja y el
        // enlace de pago no tiene sentido.
        if ($event->paymentDueAt <= $event->occurredAt) {
            return;
        }

        $this->notify('NT-04', $event, $event->patientId, $this->summary($event->appointmentId));
    }

    public function rejected(AppointmentRejected $event): void
    {
        $this->notify('NT-05', $event, $event->patientId, $this->summary($event->appointmentId) + ['motivo' => $event->reason]);
    }

    public function expired(PreAppointmentExpired $event): void
    {
        $this->notify('NT-06', $event, $event->patientId, $this->summary($event->appointmentId));
    }

    public function proofSubmitted(PaymentProofSubmitted $event): void
    {
        $this->notifyStaff('NT-07', $event, $this->summary($event->appointmentId) + ['pagos' => url('/admin/payments')]);
    }

    public function fullyBooked(AppointmentFullyBooked $event): void
    {
        $this->notify('NT-08', $event, $event->patientId, $this->summary($event->appointmentId));
    }

    public function paymentRejected(PaymentRejected $event): void
    {
        $data = $this->summary($event->appointmentId);

        $this->notify('NT-09', $event, $data['patient_id'], $data + ['motivo' => $event->reason]);
    }

    public function cancelledUnpaid(AppointmentCancelledUnpaid $event): void
    {
        $this->notify('NT-10', $event, $event->patientId, $this->summary($event->appointmentId));
    }

    public function reminder(ReminderDue $event): void
    {
        $this->notify($event->hoursBefore >= 24 ? 'NT-11' : 'NT-12', $event, $event->patientId, $this->summary($event->appointmentId), eventKey: "reminder:{$event->appointmentId}:{$event->hoursBefore}");
    }

    public function cancelled(AppointmentCancelled $event): void
    {
        $data = $this->summary($event->appointmentId);
        $data['detalle'] = $event->feeAmountCents > 0
            ? 'Se aplicó un cargo de '.Money::gtq($event->feeAmountCents)->format()." ({$event->feePercentage} % de la tarifa)."
            : 'La cancelación no tiene cargo. Si ya habías pagado, el monto queda como crédito a tu favor.';

        $this->notify($event->byTherapist ? 'NT-22' : 'NT-13', $event, $event->patientId, $data);
    }

    public function feeApplied(LateCancellationFeeApplied $event): void
    {
        $data = $this->summary($event->appointmentId);

        $this->notify('NT-14', $event, $data['patient_id'], [
            'monto' => Money::gtq($event->amountCents)->format(),
            'porcentaje' => (string) $event->percentage,
        ] + $data);
    }

    public function noteSealed(ClinicalNoteSealed $event): void
    {
        if ($event->appointmentId === null || $event->version !== 1) {
            return;
        }

        $this->notify('NT-15', $event, $event->patientId, $this->summary($event->appointmentId));
    }

    public function creditIssued(PatientCreditIssued $event): void
    {
        $data = $this->summary($event->appointmentId);

        $this->notify('NT-23', $event, $data['patient_id'], ['monto' => Money::gtq($event->amountCents)->format()] + $data);
    }

    /** @return array<class-string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            PatientAccountCreated::class => 'accountCreated',
            PreAppointmentRequested::class => 'requested',
            AppointmentApproved::class => 'approved',
            AppointmentRejected::class => 'rejected',
            PreAppointmentExpired::class => 'expired',
            PaymentProofSubmitted::class => 'proofSubmitted',
            AppointmentFullyBooked::class => 'fullyBooked',
            PaymentRejected::class => 'paymentRejected',
            AppointmentCancelledUnpaid::class => 'cancelledUnpaid',
            ReminderDue::class => 'reminder',
            AppointmentCancelled::class => 'cancelled',
            LateCancellationFeeApplied::class => 'feeApplied',
            ClinicalNoteSealed::class => 'noteSealed',
            PatientCreditIssued::class => 'creditIssued',
        ];
    }

    /** @param array<string, string> $data */
    private function notify(string $template, DomainEvent $event, string $recipientId, array $data, ?string $eventKey = null): void
    {
        $this->notifications->dispatch($template, $eventKey ?? IdempotencyKey::eventKey($event), $recipientId, $data);
    }

    /**
     * "Recepcion" es quien atiende la bandeja; si no hay nadie con ese rol, la
     * administradora.
     *
     * @param  array<string, string>  $data
     */
    private function notifyStaff(string $template, DomainEvent $event, array $data): void
    {
        $staff = $this->activeUsersWithRole('secretary') ?: $this->activeUsersWithRole('admin');

        foreach ($staff as $userId) {
            $this->notify($template, $event, $userId, $data);
        }
    }

    /** @return list<string> */
    private function activeUsersWithRole(string $role): array
    {
        return DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('roles.code', $role)
            ->where('users.is_active', true)
            ->pluck('users.id')
            ->all();
    }

    /**
     * Lo que las plantillas muestran de una cita. Nunca contenido clinico.
     *
     * @return array<string, string>
     */
    private function summary(string $appointmentId): array
    {
        $row = DB::table('appointments')
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->join('users', 'users.id', '=', 'appointments.patient_id')
            ->where('appointments.id', $appointmentId)
            ->first([
                'appointments.patient_id', 'users.name as patient', 'services.name as service',
                'appointments.starts_at', 'appointments.payment_due_at', 'appointments.fee_amount_cents',
            ]);

        return [
            'patient_id' => $row->patient_id,
            'nombre' => $row->patient,
            'paciente' => $row->patient,
            'servicio' => mb_strtolower($row->service),
            'fecha' => $this->local($row->starts_at, 'l j \d\e F'),
            'hora' => $this->local($row->starts_at, 'H:i'),
            'monto' => Money::gtq((int) $row->fee_amount_cents)->format(),
            'limite' => $row->payment_due_at ? $this->local($row->payment_due_at) : '',
            'portal' => url('/portal'),
            'sitio' => url('/'),
        ];
    }

    private function local(string $utc, string $format = 'j \d\e F \a \l\a\s H:i'): string
    {
        return Carbon::parse($utc, 'UTC')->tz(config('clinic.timezone'))->locale('es')->translatedFormat($format);
    }
}
