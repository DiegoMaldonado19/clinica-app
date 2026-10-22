<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Scheduling\Application\BookingService;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Los horarios que ofrece el calendario ese día, como opciones de un Select. */
final class SlotOptions
{
    /**
     * Recepción agenda el mismo día; el paciente, con RN-01 y RN-02.
     *
     * @return array<int, string> marca de tiempo UTC => hora local
     */
    public static function for(?string $serviceId, ?string $date, bool $assisted): array
    {
        if ($serviceId === null || $date === null) {
            return [];
        }

        $slots = app(BookingService::class)->bookableSlots(
            $serviceId, (string) DB::table('therapists')->value('id'),
            Carbon::parse($date, config('clinic.timezone'))->toDateTimeImmutable(), $assisted,
        );

        return collect($slots)->mapWithKeys(fn (TimeSlot $slot): array => [
            $slot->startsAt->getTimestamp() => Carbon::instance($slot->startsAt)->tz(config('clinic.timezone'))->format('H:i'),
        ])->all();
    }
}
