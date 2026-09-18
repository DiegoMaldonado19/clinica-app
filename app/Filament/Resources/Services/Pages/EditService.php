<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use App\Models\Service;
use App\Shared\Infrastructure\Audit\AuditLog;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/**
 * La tarifa no se edita: se programa una nueva con fecha de vigencia. La cita ya
 * solicitada conserva la tarifa con la que se pidio.
 */
class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('schedulePrice')
                ->label('Programar nueva tarifa')
                ->schema([
                    TextInput::make('amount')->label('Tarifa (Q)')->numeric()->minValue(1)->required(),
                    DateTimePicker::make('effective_from')->label('Vigente desde')->seconds(false)->default(now())->required(),
                ])
                ->action(function (array $data) {
                    /** @var Service $service */
                    $service = $this->record;
                    $cents = (int) round((float) $data['amount'] * 100);

                    $service->prices()->create(['amount_cents' => $cents, 'currency' => 'GTQ', 'effective_from' => $data['effective_from']]);
                    AuditLog::record('settings.changed', 'service', $service->id, ['price_cents' => $cents, 'effective_from' => $data['effective_from']]);
                    Notification::make()->success()->title('Tarifa programada.')->send();
                }),
        ];
    }
}
