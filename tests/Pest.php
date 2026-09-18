<?php

declare(strict_types=1);

use App\Scheduling\Application\BookingService;
use App\Scheduling\Application\RequestPreAppointment;
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
