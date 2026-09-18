<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Actions\AppointmentActions;
use App\Filament\Support\StatusBadge;
use App\Models\Appointment;
use App\Shared\Domain\ValueObject\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * D-02 · HU-04. Ordenada por el SLA que queda, no por fecha de creacion: lo
 * primero que se ve es lo que esta por vencer.
 */
class ApprovalInbox extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Bandeja de aprobaciones';

    protected static ?string $title = 'Bandeja de aprobaciones';

    protected static ?int $navigationSort = -1;

    protected string $view = 'filament.pages.approval-inbox';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAbility('appointment.approve');
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = Appointment::query()->inStatus(['SOLICITADA'])->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Appointment::query()
                ->inStatus(['SOLICITADA'])
                ->with(['patient:id,name,email,phone_e164', 'patient.patient:id,nit', 'service:id,name,duration_minutes', 'status:id,code,label'])
                ->select('appointments.*')
                // HU-03: paciente nuevo y posible duplicado por telefono, en la misma consulta.
                ->selectSub('select count(*) from appointments a2 where a2.patient_id = appointments.patient_id and a2.id <> appointments.id', 'previous_count')
                ->selectSub(DB::table('users as u1')
                    ->join('users as u2', 'u2.phone_e164', '=', 'u1.phone_e164')
                    ->whereColumn('u1.id', 'appointments.patient_id')
                    ->whereColumn('u2.id', '<>', 'u1.id')
                    ->selectRaw('count(*)'), 'duplicate_phone_count')
                ->orderBy('hold_expires_at'))
            ->heading(fn (): string => Appointment::query()->inStatus(['SOLICITADA'])->count().' pendientes')
            ->columns([
                TextColumn::make('hold_expires_at')
                    ->label('SLA')
                    ->state(fn (Appointment $record): string => self::remaining($record))
                    ->badge()
                    ->color(fn (Appointment $record): string => StatusBadge::sla(self::minutesLeft($record))[0])
                    ->icon(fn (Appointment $record): Heroicon => StatusBadge::sla(self::minutesLeft($record))[1]),
                TextColumn::make('patient.name')
                    ->label('Paciente')
                    ->description(fn (Appointment $record): ?string => match (true) {
                        (int) $record->getAttribute('duplicate_phone_count') > 0 => 'Posible duplicado: otro paciente usa este teléfono',
                        (int) $record->getAttribute('previous_count') === 0 => 'Nuevo',
                        default => null,
                    }),
                TextColumn::make('service.name')->label('Servicio'),
                TextColumn::make('starts_at')->label('Horario solicitado')->dateTime('D j M · H:i'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Detalle')
                    ->slideOver()
                    ->schema(fn (Appointment $record): array => [
                        TextEntry::make('patient.name')->label('Paciente'),
                        TextEntry::make('patient.email')->label('Correo'),
                        TextEntry::make('patient.phone_e164')->label('Teléfono'),
                        TextEntry::make('patient.patient.nit')->label('NIT')->placeholder('CF'),
                        TextEntry::make('service.name')->label('Servicio')
                            ->suffix(fn (): string => " · {$record->service->duration_minutes} minutos"),
                        TextEntry::make('starts_at')->label('Horario')->dateTime('l j \d\e F · H:i'),
                        TextEntry::make('fee_amount_cents')->label('Tarifa')
                            ->formatStateUsing(fn (int $state): string => Money::gtq($state)->format()),
                        TextEntry::make('source')->label('Origen')
                            ->formatStateUsing(fn (string $state): string => $state === 'web' ? 'Landing web' : ucfirst($state)),
                        Callout::make('Vence en '.self::remaining($record))
                            ->description('Si vence, el horario se libera automáticamente.')
                            ->color(StatusBadge::sla(self::minutesLeft($record))[0])
                            ->icon(StatusBadge::sla(self::minutesLeft($record))[1]),
                    ])
                    ->extraModalFooterActions([AppointmentActions::approve(), AppointmentActions::reject()]),
                AppointmentActions::approve(),
                AppointmentActions::reject(),
            ])
            ->toolbarActions([AppointmentActions::approveSelected()])
            ->emptyStateHeading('No hay solicitudes pendientes')
            ->paginated(false)
            ->poll('60s');
    }

    private static function minutesLeft(Appointment $record): int
    {
        return (int) now()->diffInMinutes($record->hold_expires_at, false);
    }

    private static function remaining(Appointment $record): string
    {
        $minutes = max(0, self::minutesLeft($record));

        return intdiv($minutes, 60).' h '.($minutes % 60).' min';
    }
}
