<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Faq;
use App\Models\Service;
use App\Models\User;
use App\Notifications\Infrastructure\NotificationDispatcher;
use App\Scheduling\Application\BookingService;
use App\Scheduling\Application\RequestPreAppointment;
use App\Scheduling\Domain\Exception\SlotUnavailable;
use App\Scheduling\Domain\ValueObject\BookingSource;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use App\Shared\Domain\BusinessRules;
use App\Shared\Domain\ValueObject\Nit;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Support\BookingContact;
use App\Support\BookingThrottle;
use App\Support\PolicyText;
use DateTimeInterface;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * ADR-012 · RN-19: el asistente agenda por aquí, con las mismas reglas que la
 * landing. Nunca toca la base de datos por su cuenta. Contrato: docs/api/openapi.yaml.
 */
final class ChatbotController
{
    /** Lo que muestra la alerta NT-24: la categoría, nunca las palabras de la persona (RF-58). */
    private const SIGNALS = [
        'autolesion' => 'autolesión o ideación suicida',
        'desesperanza' => 'desesperanza',
        'riesgo_terceros' => 'riesgo para otras personas',
        'peligro' => 'peligro inmediato',
        'asistente' => 'detectada por el modelo del asistente',
    ];

    public function knowledge(PolicyText $policy): JsonResponse
    {
        return response()->json([
            'clinic' => [
                'name' => config('clinic.name'),
                'address' => config('clinic.address'),
                'phone' => config('clinic.phone'),
                'email' => config('clinic.email'),
                'crisis_line' => config('clinic.crisis_line'),
            ],
            'services' => Service::query()->where('is_active', true)->with('prices')->orderBy('name')->get()
                ->map(fn (Service $service): array => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'duration_minutes' => $service->duration_minutes,
                    'price' => $service->currentPrice()?->format(),
                ]),
            'hours' => DB::table('availability_rules')->orderBy('weekday')->orderBy('starts_at')->get()
                ->map(fn (object $rule): array => [
                    'day' => now()->isoWeekday((int) $rule->weekday)->locale('es')->dayName,
                    'from' => substr((string) $rule->starts_at, 0, 5),
                    'to' => substr((string) $rule->ends_at, 0, 5),
                ]),
            'policies' => [
                'cancellation' => $policy->cancellationTiers(),
                'payment_deadline_hours' => $policy->paymentDeadlineHours(),
                'approval_sla_hours' => $policy->approvalSlaHours(),
            ],
            'faqs' => Faq::query()->orderBy('position')->get(['question', 'answer']),
        ]);
    }

    /** RN-01 y RN-02: los mismos horarios que ofrece el calendario de la landing. */
    public function availability(Request $request, BookingService $booking): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'string', Rule::exists('services', 'id')->where('is_active', true)],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);
        $day = Carbon::parse($data['date'], config('clinic.timezone'));

        return response()->json([
            'date' => $data['date'],
            'slots' => array_map(fn (TimeSlot $slot): array => [
                'starts_at' => Carbon::instance($slot->startsAt)->utc()->format(DateTimeInterface::ATOM),
                'time' => Carbon::instance($slot->startsAt)->tz(config('clinic.timezone'))->format('H:i'),
            ], $booking->bookableSlots($data['service_id'], $this->therapistId(), $day->toDateTimeImmutable())),
            'notice' => $day->isToday() ? 'Las citas para el mismo día se coordinan por teléfono al '.config('clinic.phone').'.' : null,
        ]);
    }

    /** HU-25: la persona ya confirmó sus datos y aceptó la política en el formulario del widget. */
    public function appointments(Request $request, BookingService $booking, PolicyText $policy): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'ip_address' => ['nullable', 'ip'],
        ] + BookingContact::rules(), BookingContact::messages());

        if (BookingThrottle::tooMany($data['ip_address'] ?? null, $data['email'])) {
            return response()->json(['message' => 'Recibimos varias solicitudes seguidas. Espera unos minutos o llámanos al '.config('clinic.phone').'.'], 429);
        }

        $startsAt = Carbon::parse($data['starts_at'])->utc();

        try {
            DB::transaction(fn (): string => $booking->request(new RequestPreAppointment(
                serviceId: $data['service_id'],
                therapistId: $this->therapistId(),
                startsAt: $startsAt->toDateTimeImmutable(),
                name: trim($data['name']),
                email: $data['email'],
                phoneE164: PhoneNumber::fromGuatemalan($data['phone'])->e164,
                nit: Nit::from($data['nit'])->value,
                consentVersions: PolicyText::consentVersions(),
                ipAddress: $data['ip_address'] ?? null,
                source: BookingSource::CHAT,
            )));
        } catch (SlotUnavailable $exception) {
            return response()->json(['message' => $exception->getMessage().' Pídele al asistente otro horario.'], 409);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $service = (string) Service::query()->whereKey($data['service_id'])->value('name');
        $when = $startsAt->tz(config('clinic.timezone'))->locale('es')->translatedFormat('l j \d\e F \a \l\a\s H:i');

        return response()->json([
            'message' => "Recibimos tu solicitud de {$service} para el {$when}. Te confirmaremos por correo en menos de "
                .$policy->approvalSlaHours().' horas.',
        ], 201);
    }

    /** RN-20 · NT-24: aviso urgente a la psicóloga, aun dentro de la ventana de silencio. */
    public function crisisAlert(Request $request, NotificationDispatcher $notifications, BusinessRules $rules): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'string', 'max:64'],
            'signal' => ['required', Rule::in(array_keys(self::SIGNALS))],
        ]);

        // El parámetro activa el aviso; la línea de crisis y el cierre del chat no dependen de él.
        if (! $rules->value('RN-20', 'crisis_escalation_enabled')) {
            return response()->json(['notified' => 0], 202);
        }

        $now = now(config('clinic.timezone'))->locale('es');
        $recipients = User::query()->whereRelation('role', 'code', 'admin')->where('is_active', true)->pluck('id');

        foreach ($recipients as $recipient) {
            $notifications->dispatch('NT-24', "crisis:{$data['session_id']}", (string) $recipient, [
                'fecha' => $now->translatedFormat('j \d\e F'),
                'hora' => $now->format('H:i'),
                'senal' => self::SIGNALS[$data['signal']],
                'sesion' => $data['session_id'],
            ]);
        }

        return response()->json(['notified' => $recipients->count()], 202);
    }

    private function therapistId(): string
    {
        return (string) DB::table('therapists')->value('id');
    }
}
