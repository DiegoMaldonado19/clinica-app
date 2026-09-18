<?php

declare(strict_types=1);

namespace App\Scheduling\Application;

use App\Scheduling\Domain\Exception\BookingNotAllowed;
use App\Scheduling\Domain\Exception\SlotUnavailable;
use App\Scheduling\Domain\Model\Appointment;
use App\Scheduling\Domain\Port\AppointmentRepository;
use App\Scheduling\Domain\Port\AvailabilityProvider;
use App\Scheduling\Domain\Port\PatientRegistry;
use App\Scheduling\Domain\Port\ServiceCatalog;
use App\Scheduling\Domain\Port\SlotLockManager;
use App\Scheduling\Domain\ValueObject\BookingSource;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use DomainException;

/**
 * Crea citas: la solicitud publica (to-be-01, doc 09 §1) y el agendamiento
 * asistido por recepcion (to-be-09). Orquesta; las reglas las decide el agregado.
 */
final readonly class BookingService
{
    public function __construct(
        private AppointmentRepository $appointments,
        private AvailabilityProvider $availability,
        private SlotLockManager $locks,
        private PatientRegistry $patients,
        private ServiceCatalog $services,
        private SchedulingPolicies $policies,
        private EventBus $events,
        private ClockInterface $clock,
    ) {}

    public function request(RequestPreAppointment $command): string
    {
        [$slot, $fee] = $this->offeredSlot($command->serviceId, $command->therapistId, $command->startsAt);

        return $this->locks->exclusively($command->therapistId, $slot, function () use ($command, $slot, $fee): string {
            $patientId = $this->patients->findOrRegister($command->name, $command->email, $command->phoneE164, $command->nit);
            $this->patients->recordConsents($patientId, $command->consentVersions, $command->ipAddress);

            $appointment = Appointment::request(
                $this->appointments->nextId(), $patientId, $command->therapistId, $command->serviceId,
                $slot, $fee, BookingSource::WEB,
                $this->policies->bookingWindow(), $this->policies->approvalSla(), $this->clock,
            );

            return $this->persist($appointment);
        });
    }

    public function bookAssisted(
        string $patientId,
        string $serviceId,
        string $therapistId,
        DateTimeImmutable $startsAt,
        BookingSource $source,
        string $bookedBy,
    ): string {
        [$slot, $fee] = $this->offeredSlot($serviceId, $therapistId, $startsAt);

        return $this->locks->exclusively($therapistId, $slot, fn (): string => $this->persist(Appointment::bookAssisted(
            $this->appointments->nextId(), $patientId, $therapistId, $serviceId, $slot, $fee,
            $source, $bookedBy, $this->policies->paymentDeadlineHours(), $this->clock,
        )));
    }

    /**
     * Lo que el calendario ofrece ese dia: por la web respeta RN-01/RN-02;
     * recepcion puede agendar el mismo dia, pero nunca en el pasado.
     *
     * @return list<TimeSlot>
     */
    public function bookableSlots(string $serviceId, string $therapistId, DateTimeImmutable $day, bool $assisted = false): array
    {
        $quote = $this->services->quote($serviceId, $this->clock->now());

        if ($quote === null) {
            return [];
        }

        $now = $this->clock->now();
        $window = $this->policies->bookingWindow();

        return array_values(array_filter(
            $this->availability->freeSlots($therapistId, $day->setTimezone($this->policies->timezone)->setTime(0, 0), $quote['durationMinutes']),
            function (TimeSlot $slot) use ($assisted, $now, $window): bool {
                if ($assisted) {
                    return $slot->startsAt > $now;
                }

                try {
                    $window->assertBookable($slot, $now);

                    return true;
                } catch (BookingNotAllowed) {
                    return false;
                }
            },
        ));
    }

    /**
     * Solo se agenda un horario que el calendario ofrece: el cliente no puede
     * inventar uno fuera del horario ni encima de un bloqueo.
     *
     * @return array{0: TimeSlot, 1: Money}
     */
    private function offeredSlot(string $serviceId, string $therapistId, DateTimeImmutable $startsAt): array
    {
        $quote = $this->services->quote($serviceId, $this->clock->now())
            ?? throw new DomainException('El servicio elegido no esta disponible.');

        $day = $startsAt->setTimezone($this->policies->timezone)->setTime(0, 0);

        foreach ($this->availability->freeSlots($therapistId, $day, $quote['durationMinutes']) as $slot) {
            if ($slot->startsAt == $startsAt) {
                return [$slot, $quote['fee']];
            }
        }

        throw new SlotUnavailable;
    }

    private function persist(Appointment $appointment): string
    {
        $this->appointments->save($appointment);
        $this->events->publish(...$appointment->releaseEvents());

        return $appointment->id;
    }
}
