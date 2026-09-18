<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure;

use App\Billing\Domain\Event\PaymentApproved;
use App\Billing\Domain\Event\PaymentProofSubmitted;
use App\Billing\Domain\Event\PaymentRejected;
use App\ClinicalRecords\Domain\Event\ClinicalNoteSealed;
use App\Scheduling\Application\AppointmentTransitions;
use App\Scheduling\Application\SchedulingPolicies;
use App\Scheduling\Domain\Event\AppointmentApproved;
use App\Scheduling\Domain\Event\PreAppointmentRequested;
use App\Scheduling\Infrastructure\Job\CancelUnpaidJob;
use App\Scheduling\Infrastructure\Job\ExpirePreAppointmentJob;
use App\Scheduling\Infrastructure\Job\SendReminderJob;
use App\Shared\Domain\Clock\ClockInterface;
use Illuminate\Events\Dispatcher;

/**
 * Lo que Scheduling hace ante eventos propios y ajenos. Los plazos se programan
 * como trabajos diferidos; `appointments:reconcile` recupera los que se pierdan.
 */
final readonly class SchedulingEventSubscriber
{
    public function __construct(
        private AppointmentTransitions $transitions,
        private SchedulingPolicies $policies,
        private ClockInterface $clock,
    ) {}

    public function onRequested(PreAppointmentRequested $event): void
    {
        ExpirePreAppointmentJob::dispatch($event->appointmentId)->delay($event->holdExpiresAt);
    }

    public function onApproved(AppointmentApproved $event): void
    {
        CancelUnpaidJob::dispatch($event->appointmentId)->delay($event->paymentDueAt);

        foreach ($this->policies->reminderOffsets() as $hours) {
            $at = $event->startsAt->modify("-{$hours} hours");

            if ($at > $this->clock->now()) {
                SendReminderJob::dispatch($event->appointmentId, $hours)->delay($at);
            }
        }
    }

    public function onProofSubmitted(PaymentProofSubmitted $event): void
    {
        $this->transitions->submitPayment($event->appointmentId);
    }

    public function onPaymentApproved(PaymentApproved $event): void
    {
        $this->transitions->confirmPayment($event->appointmentId);
    }

    public function onPaymentRejected(PaymentRejected $event): void
    {
        $this->transitions->rejectPayment($event->appointmentId);
    }

    public function onNoteSealed(ClinicalNoteSealed $event): void
    {
        if ($event->appointmentId !== null && $event->version === 1) {
            $this->transitions->complete($event->appointmentId);
        }
    }

    /** @return array<class-string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            PreAppointmentRequested::class => 'onRequested',
            AppointmentApproved::class => 'onApproved',
            PaymentProofSubmitted::class => 'onProofSubmitted',
            PaymentApproved::class => 'onPaymentApproved',
            PaymentRejected::class => 'onPaymentRejected',
            ClinicalNoteSealed::class => 'onNoteSealed',
        ];
    }
}
