<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\AppointmentStatus;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Agendar por teléfono')];
    }

    public function getTabs(): array
    {
        $tabs = [
            'Próximas' => ['SOLICITADA', 'CONFIRMADA_PENDIENTE_PAGO', 'PAGO_EN_REVISION', 'PAGO_EN_CAJA', 'AGENDADA', 'EN_CURSO'],
            'Pago pendiente' => ['CONFIRMADA_PENDIENTE_PAGO', 'PAGO_EN_REVISION', 'PAGO_EN_CAJA'],
            'Agendadas' => ['AGENDADA', 'EN_CURSO'],
            'Historial' => ['ATENDIDA', 'RECHAZADA', 'EXPIRADA', 'CANCELADA_IMPAGO', 'CANCELADA_SIN_CARGO', 'CANCELADA_CON_RECARGO', 'NO_SHOW'],
        ];

        return array_map(
            fn (array $codes): Tab => Tab::make()->modifyQueryUsing(
                fn (Builder $query) => $query->whereIn('status_id', AppointmentStatus::query()->whereIn('code', $codes)->select('id')),
            ),
            $tabs,
        );
    }
}
