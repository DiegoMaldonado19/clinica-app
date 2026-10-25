<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Service;
use App\Scheduling\Application\BookingService;
use App\Scheduling\Application\RequestPreAppointment;
use App\Scheduling\Domain\Exception\BookingNotAllowed;
use App\Scheduling\Domain\Exception\SlotUnavailable;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use App\Shared\Domain\ValueObject\Nit;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Support\BookingContact;
use App\Support\BookingThrottle;
use App\Support\PolicyText;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Throwable;

/**
 * Wizard publico A-01 -> A-04. Todo lo que llega del navegador se vuelve a
 * validar en BookingService: aqui solo se guia a la persona.
 *
 * @property-read Collection<int, Service> $services
 * @property-read Service|null $service
 */
final class BookingWizard extends Component
{
    public int $step = 1;

    public ?string $serviceId = null;

    public string $month = '';

    public ?string $day = null;

    public ?int $slot = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $nit = '';

    public bool $accepted = false;

    public ?string $notice = null;

    /** `error` cuando algo impidio la solicitud; `info` cuando solo orienta. */
    public string $noticeTone = 'info';

    public function mount(?string $serviceId = null): void
    {
        $this->month = $this->today()->format('Y-m');

        if ($serviceId !== null && $this->services->contains('id', $serviceId)) {
            $this->chooseService($serviceId);
        } else {
            $this->serviceId = null;
        }
    }

    public function chooseService(string $id): void
    {
        abort_unless($this->services->contains('id', $id), 404);

        $this->serviceId = $id;
        $this->reset('day', 'slot', 'notice');
        $this->step = 2;
    }

    public function changeMonth(int $offset): void
    {
        $month = Carbon::parse("{$this->month}-01", $this->timezone())->addMonthsNoOverflow($offset);
        $first = $this->today()->startOfMonth();

        if ($month->betweenIncluded($first, $first->copy()->addMonths(2))) {
            $this->month = $month->format('Y-m');
            $this->reset('day', 'slot');
        }
    }

    public function chooseDay(string $date): void
    {
        $this->notice = $date === $this->today()->toDateString()
            ? 'Las citas para el mismo día se coordinan por teléfono. Llámanos al '.config('clinic.phone').' y con gusto te atendemos.'
            : null;
        $this->noticeTone = 'info';
        $this->day = $this->notice === null ? $date : null;
        $this->slot = null;
    }

    public function chooseSlot(int $timestamp): void
    {
        $this->slot = $timestamp;
        $this->notice = null;
        $this->step = 3;
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function submit(BookingService $booking): void
    {
        $this->validate(BookingContact::rules(), BookingContact::messages());

        if (BookingThrottle::tooMany(request()->ip(), $this->email)) {
            $this->fail('Recibimos varias solicitudes seguidas. Espera unos minutos o llámanos al '.config('clinic.phone').'.');

            return;
        }

        try {
            DB::transaction(fn () => $booking->request(new RequestPreAppointment(
                serviceId: (string) $this->serviceId,
                therapistId: $this->therapistId(),
                startsAt: Carbon::createFromTimestamp((int) $this->slot, 'UTC')->toDateTimeImmutable(),
                name: trim($this->name),
                email: $this->email,
                phoneE164: PhoneNumber::fromGuatemalan($this->phone)->e164,
                nit: Nit::from($this->nit)->value,
                consentVersions: PolicyText::consentVersions(),
                ipAddress: request()->ip(),
            )));
        } catch (SlotUnavailable) {
            // MSG-06: se conservan los datos y se vuelve al calendario.
            $this->fail('Ese horario se acaba de ocupar. Elige otro: te mostramos los más cercanos. Tus datos se conservaron.');
            $this->step = 2;
            $this->day = Carbon::createFromTimestamp((int) $this->slot, $this->timezone())->toDateString();
            $this->slot = null;

            return;
        } catch (BookingNotAllowed|DomainException $exception) {
            $this->fail($exception->getMessage());
            $this->step = 2;

            return;
        } catch (Throwable $exception) {
            Log::error('La solicitud de cita no se pudo registrar', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
            $this->fail('No pudimos enviar tu solicitud. Tus datos se conservaron: intenta de nuevo en unos minutos o llámanos al '.config('clinic.phone').'.');

            return;
        }

        $this->step = 4;
    }

    private function fail(string $message): void
    {
        $this->notice = $message;
        $this->noticeTone = 'error';
    }

    /** @return Collection<int, Service> */
    #[Computed]
    public function services(): Collection
    {
        return Service::query()->where('is_active', true)->with('prices')->orderBy('duration_minutes')->get();
    }

    #[Computed]
    public function service(): ?Service
    {
        return $this->services->firstWhere('id', $this->serviceId);
    }

    /**
     * Las semanas del mes con el estado de cada dia: disponible, pocos cupos,
     * sin cupo o no disponible (A-02).
     *
     * @return list<list<array{date: string, day: int, state: string}|null>>
     */
    #[Computed]
    public function calendar(): array
    {
        $first = Carbon::parse("{$this->month}-01", $this->timezone());
        $cells = array_fill(0, $first->dayOfWeekIso - 1, null);

        for ($date = $first->copy(); $date->month === $first->month; $date->addDay()) {
            $cells[] = ['date' => $date->toDateString(), 'day' => $date->day, 'state' => $this->dayState($date)];
        }

        return array_chunk(array_pad($cells, (int) ceil(count($cells) / 7) * 7, null), 7);
    }

    /**
     * No se llama `slots`: Livewire 4 ya tiene una propiedad protegida con ese
     * nombre, y la vista la leeria vacia en lugar de esta.
     *
     * @return list<TimeSlot>
     */
    #[Computed]
    public function times(): array
    {
        return $this->day === null ? [] : $this->bookable(Carbon::parse($this->day, $this->timezone()));
    }

    /**
     * Los tres horarios libres mas cercanos al elegido (MSG-06, RF-08).
     *
     * @return list<TimeSlot>
     */
    #[Computed]
    public function nearest(): array
    {
        if ($this->day === null || $this->notice === null) {
            return [];
        }

        $found = [];

        for ($date = Carbon::parse($this->day, $this->timezone()), $i = 0; $i < 14 && count($found) < 3; $i++, $date->addDay()) {
            $found = [...$found, ...$this->bookable($date)];
        }

        return array_slice($found, 0, 3);
    }

    public function render(PolicyText $policy): View
    {
        return view('livewire.booking-wizard', [
            'policy' => $policy,
            'timezone' => $this->timezone(),
            'chosen' => $this->slot ? Carbon::createFromTimestamp($this->slot, $this->timezone()) : null,
        ]);
    }

    private function dayState(Carbon $date): string
    {
        if ($date->lte($this->today())) {
            return 'unavailable';
        }

        $count = count($this->bookable($date));

        return match (true) {
            $count === 0 && $date->isSunday() => 'unavailable',
            $count === 0 => 'full',
            $count <= 2 => 'few',
            default => 'available',
        };
    }

    /** @return list<TimeSlot> */
    private function bookable(Carbon $date): array
    {
        if ($this->serviceId === null) {
            return [];
        }

        return app(BookingService::class)->bookableSlots($this->serviceId, $this->therapistId(), $date->toDateTimeImmutable());
    }

    private function therapistId(): string
    {
        return (string) DB::table('therapists')->value('id');
    }

    private function today(): Carbon
    {
        return now($this->timezone())->startOfDay();
    }

    private function timezone(): string
    {
        return config('clinic.timezone');
    }
}
