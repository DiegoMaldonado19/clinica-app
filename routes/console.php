<?php

use App\ClinicalRecords\Application\PsychTestService;
use App\ClinicalRecords\Domain\Port\PsychTestAssignmentRepository;
use App\Scheduling\Application\AppointmentTransitions;
use App\Scheduling\Domain\Port\AppointmentRepository;
use App\Shared\Domain\Clock\ClockInterface;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 | T-10, doc 09 §10. Red de seguridad de los trabajos diferidos: si el procesador
 | de colas estuvo caido, recupera expiraciones e impagos. Ademas cierra las
 | sesiones terminadas (ADR-039). Es idempotente porque los handlers verifican el
 | estado antes de actuar.
 */
Artisan::command('appointments:reconcile', function (AppointmentRepository $appointments, AppointmentTransitions $transitions, ClockInterface $clock) {
    $now = $clock->now();

    $expired = collect($appointments->idsWithExpiredHolds($now))
        ->filter(fn (string $id) => DB::transaction(fn () => $transitions->expireIfDue($id)));
    $unpaid = collect($appointments->idsWithOverduePayments($now))
        ->filter(fn (string $id) => DB::transaction(fn () => $transitions->cancelIfUnpaid($id)));
    $attended = collect($appointments->idsWithEndedSessions($now))
        ->filter(fn (string $id) => DB::transaction(fn () => $transitions->completeIfInProgress($id)));
    $missed = collect($appointments->idsWithMissedSessions($now))
        ->filter(fn (string $id) => DB::transaction(fn () => $transitions->markNoShowIfEnded($id)));

    $this->info("Expiradas: {$expired->count()} · Canceladas por impago: {$unpaid->count()} · Atendidas: {$attended->count()} · Inasistencias: {$missed->count()}");
})->purpose('Aplica los plazos de la cita: expiraciones, impagos y sesiones terminadas');

// HU-13: la prueba sin responder se marca vencida una sola vez y avisa con NT-17.
Artisan::command('psych-tests:expire', function (PsychTestAssignmentRepository $assignments, PsychTestService $tests, ClockInterface $clock) {
    $expired = collect($assignments->idsPastDue($clock->now()))
        ->filter(fn (string $id) => DB::transaction(fn () => $tests->expire($id)));

    $this->info("Pruebas vencidas: {$expired->count()}");
})->purpose('Marca como vencidas las pruebas psicometricas sin responder');

// El bloqueo del planificador vive en la base, no en Redis: con dos instancias,
// es lo que evita que una tarea corra dos veces (doc 03 §2.3, CA-33).
Schedule::useCache('database');

Schedule::command('appointments:reconcile')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('psych-tests:expire')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
