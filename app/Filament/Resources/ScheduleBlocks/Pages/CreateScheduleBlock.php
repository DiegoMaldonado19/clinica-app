<?php

namespace App\Filament\Resources\ScheduleBlocks\Pages;

use App\Filament\Resources\ScheduleBlocks\ScheduleBlockResource;
use App\Models\Appointment;
use App\Models\ScheduleBlock;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RN-18: no se publica un bloqueo con citas activas dentro. Cada una se resuelve
 * antes, de forma explicita, con "Cancelar por indisponibilidad".
 */
class CreateScheduleBlock extends CreateRecord
{
    protected static string $resource = ScheduleBlockResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Las que todavia ocupan el horario y se pueden resolver; una inasistencia
        // o una sesion ya atendida no se cancelan.
        $affected = Appointment::query()
            ->with('patient:id,name')
            ->inStatus(['SOLICITADA', 'CONFIRMADA_PENDIENTE_PAGO', 'PAGO_EN_REVISION', 'PAGO_EN_CAJA', 'AGENDADA'])
            ->where('starts_at', '<', $data['ends_at'])
            ->where('ends_at', '>', $data['starts_at'])
            ->orderBy('starts_at')
            ->get();

        if ($affected->isNotEmpty()) {
            throw ValidationException::withMessages([
                // MSG-27
                'data.starts_at' => 'Hay '.$affected->count().' citas agendadas en ese rango. Debes resolver cada una antes de completar el bloqueo («Cancelar por indisponibilidad» en Citas): '
                    .$affected->map(fn (Appointment $a): string => $a->starts_at->tz(config('clinic.timezone'))->format('d/m H:i').' '.$a->patient->name)->implode('; ').'.',
            ]);
        }

        return $data + [
            'therapist_id' => (string) DB::table('therapists')->value('id'),
            'created_by' => (string) auth()->id(),
        ];
    }

    protected function afterCreate(): void
    {
        /** @var ScheduleBlock $block */
        $block = $this->record;
        ScheduleBlockResource::forgetAvailability($block);
    }
}
