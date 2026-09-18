<?php

namespace App\Filament\Resources\Appointments;

use App\Filament\Actions\AppointmentActions;
use App\Filament\Resources\Appointments\Pages\CreateAppointment;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Resources\Appointments\Pages\ViewAppointment;
use App\Filament\Support\StatusBadge;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Shared\Domain\ValueObject\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * La entidad principal. Sin edicion ni borrado: la cita cambia solo por sus
 * transiciones de estado (AppointmentActions), y se crea por agendamiento
 * asistido (T-11).
 */
class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'cita';

    protected static ?string $pluralModelLabel = 'citas';

    protected static ?string $navigationLabel = 'Citas';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['patient:id,name,email,phone_e164', 'service:id,name', 'status:id,code,label']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at')
            ->columns([
                TextColumn::make('starts_at')->label('Horario')->dateTime('D j M Y · H:i')->sortable(),
                TextColumn::make('patient.name')->label('Paciente')->searchable(),
                TextColumn::make('service.name')->label('Servicio'),
                TextColumn::make('status.label')->label('Estado')->badge()
                    ->color(fn (Appointment $record): string => StatusBadge::color($record->statusCode()))
                    ->icon(fn (Appointment $record): Heroicon => StatusBadge::icon($record->statusCode())),
                TextColumn::make('source')->label('Origen')->badge()->color('gray'),
                TextColumn::make('fee_amount_cents')->label('Tarifa')
                    ->formatStateUsing(fn (int $state): string => Money::gtq($state)->format()),
            ])
            ->filters([
                SelectFilter::make('status_id')->label('Estado')
                    ->options(fn (): array => AppointmentStatus::query()->pluck('label', 'id')->all()),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cita')->columns(3)->schema([
                TextEntry::make('patient.name')->label('Paciente'),
                TextEntry::make('patient.email')->label('Correo'),
                TextEntry::make('patient.phone_e164')->label('Teléfono'),
                TextEntry::make('service.name')->label('Servicio'),
                TextEntry::make('starts_at')->label('Horario')->dateTime('l j \d\e F Y · H:i'),
                TextEntry::make('status.label')->label('Estado')->badge()
                    ->color(fn (Appointment $record): string => StatusBadge::color($record->statusCode()))
                    ->icon(fn (Appointment $record): Heroicon => StatusBadge::icon($record->statusCode())),
                TextEntry::make('fee_amount_cents')->label('Tarifa')
                    ->formatStateUsing(fn (int $state): string => Money::gtq($state)->format()),
                TextEntry::make('hold_expires_at')->label('Vence la aprobación')->dateTime('j M · H:i')->placeholder('—'),
                TextEntry::make('payment_due_at')->label('Límite de pago')->dateTime('j M · H:i')->placeholder('—'),
                TextEntry::make('rejection_reason')->label('Motivo del rechazo')->placeholder('—'),
            ]),
            Section::make('Historial de estados')->schema([
                RepeatableEntry::make('statusLog')->hiddenLabel()->columns(4)->schema([
                    TextEntry::make('occurred_at')->label('Cuándo')->dateTime('j M Y · H:i'),
                    TextEntry::make('toStatus.label')->label('Estado'),
                    TextEntry::make('author.name')->label('Quién')->placeholder('El sistema'),
                    TextEntry::make('reason_text')->label('Motivo')->placeholder('—'),
                ]),
            ]),
        ]);
    }

    /** @return list<Action> */
    public static function stateActions(): array
    {
        return [
            AppointmentActions::approve(),
            AppointmentActions::reject(),
            AppointmentActions::checkIn(),
            AppointmentActions::documentSession(),
            AppointmentActions::markNoShow(),
            AppointmentActions::cancel(),
            AppointmentActions::cancelByTherapist(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppointments::route('/'),
            'create' => CreateAppointment::route('/create'),
            'view' => ViewAppointment::route('/{record}'),
        ];
    }
}
