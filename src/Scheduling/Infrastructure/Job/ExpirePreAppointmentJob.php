<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Job;

use App\Scheduling\Application\AppointmentTransitions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

/** HU-05. Verifica el estado antes de actuar: correrlo tarde o dos veces no hace nada. */
final class ExpirePreAppointmentJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public readonly string $appointmentId)
    {
        $this->onQueue('critical');
    }

    public function handle(AppointmentTransitions $transitions): void
    {
        DB::transaction(fn () => $transitions->expireIfDue($this->appointmentId));
    }
}
