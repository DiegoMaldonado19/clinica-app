<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAppointment extends ViewRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return array_map(
            fn ($action) => $action->after(fn () => $this->record->refresh()),
            AppointmentResource::stateActions(),
        );
    }
}
