<?php

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
 | de colas estuvo caido, recupera expiraciones e impagos. Es idempotente porque
 | los handlers verifican el estado antes de actuar.
 */
Artisan::command('appointments:reconcile', function (AppointmentRepository $appointments, AppointmentTransitions $transitions, ClockInterface $clock) {
    $now = $clock->now();

    $expired = collect($appointments->idsWithExpiredHolds($now))
        ->filter(fn (string $id) => DB::transaction(fn () => $transitions->expireIfDue($id)));
    $unpaid = collect($appointments->idsWithOverduePayments($now))
        ->filter(fn (string $id) => DB::transaction(fn () => $transitions->cancelIfUnpaid($id)));

    // Sin no-show automatico: la inasistencia la confirma recepcion.
    $noShowCandidates = DB::table('appointments')
        ->join('appointment_statuses', 'appointment_statuses.id', '=', 'appointments.status_id')
        ->where('appointment_statuses.code', 'AGENDADA')
        ->where('appointments.ends_at', '<', $now->format('Y-m-d H:i:s'))
        ->count();

    $this->info("Expiradas: {$expired->count()} · Canceladas por impago: {$unpaid->count()} · Posibles inasistencias por revisar: {$noShowCandidates}");
})->purpose('Recupera expiraciones e impagos que los trabajos diferidos no procesaron');

// El bloqueo del planificador vive en la base, no en Redis: con dos instancias,
// es lo que evita que una tarea corra dos veces (doc 03 §2.3, CA-33).
Schedule::useCache('database');

Schedule::command('appointments:reconcile')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();
