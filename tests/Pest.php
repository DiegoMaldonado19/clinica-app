<?php

declare(strict_types=1);

use App\Billing\Application\BillingService;
use App\Billing\Domain\PaymentProof;
use App\Scheduling\Application\BookingService;
use App\Scheduling\Application\RequestPreAppointment;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
 | Unit no extiende TestCase a proposito: el dominio se prueba sin arrancar
 | Laravel ni tocar la base de datos. Si una prueba de Unit necesita el
 | framework, la frontera esta mal puesta (ADR-002).
 */

pest()->extend(TestCase::class)->in('Feature', 'Integration');

/** Un instante en hora de Guatemala, convertido a UTC como vive en la base. */
function gtAt(string $local): Carbon
{
    return Carbon::parse($local, 'America/Guatemala')->utc();
}

function therapistId(): string
{
    return (string) DB::table('therapists')->value('id');
}

function serviceId(string $name = 'Terapia individual'): string
{
    return (string) DB::table('services')->where('name', $name)->value('id');
}

/** @return list<string> horas locales que el calendario ofrece a recepcion ese dia */
function freeHours(string $date): array
{
    $slots = app(BookingService::class)->bookableSlots(
        serviceId(), therapistId(), Carbon::parse($date, 'America/Guatemala')->toDateTimeImmutable(), assisted: true,
    );

    return array_map(fn (TimeSlot $slot): string => Carbon::instance($slot->startsAt)->tz('America/Guatemala')->format('H:i'), $slots);
}

function statusOf(string $appointmentId): string
{
    return (string) DB::table('appointments')
        ->join('appointment_statuses', 'appointment_statuses.id', '=', 'appointments.status_id')
        ->where('appointments.id', $appointmentId)
        ->value('appointment_statuses.code');
}

/** Solicita una cita por la web como lo haria el wizard. */
function requestAppointment(string $startsAtLocal, string $email = 'ana.perez@correo.test'): string
{
    return DB::transaction(fn () => app(BookingService::class)->request(new RequestPreAppointment(
        serviceId: serviceId(),
        therapistId: therapistId(),
        startsAt: gtAt($startsAtLocal)->toDateTimeImmutable(),
        name: 'Ana Pérez',
        email: $email,
        phoneE164: '+50255551234',
        nit: 'CF',
        consentVersions: ['CANCELACION' => '2026-09', 'PRIVACIDAD' => '2026-09'],
        ipAddress: '10.0.0.7',
    )));
}

/** @return list<string> plantillas NT registradas, en orden */
function dispatchedTemplates(?string $recipientId = null): array
{
    return DB::table('notification_dispatches')
        ->when($recipientId, fn ($q) => $q->where('recipient_id', $recipientId))
        ->orderBy('id')
        ->pluck('template_code')
        ->all();
}

/** Sube un comprobante y lo aprueba: la cita queda AGENDADA. */
function payAndBook(string $appointmentId, string $staffId): void
{
    $billing = app(BillingService::class);
    $billing->submitProof($appointmentId, new PaymentProof(
        (string) random_int(1, 999999), (int) DB::table('banks')->value('id'), '4471',
        new DateTimeImmutable('2026-09-15'), 'comprobantes/x.pdf', str_repeat('b', 64), 1000,
    ));
    $billing->approve((string) DB::table('payments')->where('appointment_id', $appointmentId)->value('id'), $staffId);
}
