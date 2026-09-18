<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure;

use App\Billing\Application\BillingService;
use App\Scheduling\Domain\Event\AppointmentApproved;
use App\Scheduling\Domain\Event\AppointmentCancelled;
use App\Scheduling\Domain\Event\AppointmentCheckedIn;
use App\Scheduling\Domain\Event\PayAtDeskChosen;
use App\Shared\Domain\ValueObject\Money;
use Illuminate\Events\Dispatcher;

/** Billing reacciona a Scheduling solo por eventos (CLAUDE.md). */
final readonly class BillingEventSubscriber
{
    public function __construct(private BillingService $billing) {}

    public function onApproved(AppointmentApproved $event): void
    {
        $this->billing->openSessionPayment($event->appointmentId, new Money($event->feeAmountCents, $event->feeCurrency));
    }

    public function onPayAtDesk(PayAtDeskChosen $event): void
    {
        $this->billing->expectAtDesk($event->appointmentId);
    }

    public function onCheckedIn(AppointmentCheckedIn $event): void
    {
        if ($event->paidAtDesk) {
            $this->billing->collectAtDesk($event->appointmentId, $event->checkedInBy);
        }
    }

    public function onCancelled(AppointmentCancelled $event): void
    {
        $this->billing->settleCancellation($event->appointmentId, $event->feeAmountCents, $event->feePercentage);
    }

    /** @return array<class-string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            AppointmentApproved::class => 'onApproved',
            PayAtDeskChosen::class => 'onPayAtDesk',
            AppointmentCheckedIn::class => 'onCheckedIn',
            AppointmentCancelled::class => 'onCancelled',
        ];
    }
}
