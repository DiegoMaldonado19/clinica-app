<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Scheduling\Application\BookingService;
use App\Scheduling\Domain\ValueObject\BookingSource;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use DomainException;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * T-11 · to-be-09: recepcion agenda por telefono o en persona. Es la excepcion
 * a RN-01: puede agendar el mismo dia. La cita nace aprobada.
 */
class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;

    protected static ?string $title = 'Agendar por teléfono o en recepción';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('patient_id')->label('Paciente')
                ->options(fn (): array => User::query()->whereRelation('role', 'code', 'patient')->whereHas('patient')->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->required()
                ->helperText('Si es nuevo, dalo de alta primero en Usuarios y Pacientes.'),
            Select::make('service_id')->label('Servicio')
                ->options(fn (): array => Service::query()->where('is_active', true)->pluck('name', 'id')->all())
                ->required()
                ->live(),
            DatePicker::make('date')->label('Fecha')
                ->minDate(now(config('clinic.timezone'))->startOfDay())
                ->native(false)
                ->required()
                ->live(),
            Select::make('starts_at')->label('Hora')
                ->options(fn (Get $get): array => self::freeTimes($get('service_id'), $get('date')))
                ->helperText('Solo se ofrecen horarios libres; los bloqueos de agenda ya están descontados.')
                ->required(),
            Radio::make('source')->label('¿Cómo llegó la solicitud?')
                ->options([BookingSource::PHONE->value => 'Por teléfono', BookingSource::WALK_IN->value => 'En recepción'])
                ->default(BookingSource::PHONE->value)
                ->required(),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return Appointment::findOrFail(DB::transaction(fn (): string => app(BookingService::class)->bookAssisted(
                $data['patient_id'],
                $data['service_id'],
                (string) DB::table('therapists')->value('id'),
                Carbon::createFromTimestamp((int) $data['starts_at'], 'UTC')->toDateTimeImmutable(),
                BookingSource::from($data['source']),
                (string) auth()->id(),
            )));
        } catch (DomainException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }
    }

    protected function getRedirectUrl(): string
    {
        return AppointmentResource::getUrl('view', ['record' => $this->record]);
    }

    /** @return array<int, string> marca de tiempo UTC => hora local */
    private static function freeTimes(?string $serviceId, ?string $date): array
    {
        if ($serviceId === null || $date === null) {
            return [];
        }

        $slots = app(BookingService::class)->bookableSlots(
            $serviceId, (string) DB::table('therapists')->value('id'),
            Carbon::parse($date, config('clinic.timezone'))->toDateTimeImmutable(), assisted: true,
        );

        return collect($slots)->mapWithKeys(fn (TimeSlot $slot): array => [
            $slot->startsAt->getTimestamp() => Carbon::instance($slot->startsAt)->tz(config('clinic.timezone'))->format('H:i'),
        ])->all();
    }
}
