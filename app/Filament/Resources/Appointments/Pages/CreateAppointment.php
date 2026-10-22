<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Schemas\PersonFields;
use App\Filament\Support\DomainCommand;
use App\Filament\Support\SlotOptions;
use App\Identity\Infrastructure\DatabasePatientRegistry;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Scheduling\Application\BookingService;
use App\Scheduling\Domain\ValueObject\BookingSource;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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

    /** @var list<string> se calcula al elegir el servicio, no en cada peticion */
    public array $disabledDates = [];

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('patient_id')->label('Paciente')
                ->options(fn (): array => User::query()->whereRelation('role', 'code', 'patient')->whereHas('patient')->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->required()
                ->helperText('¿Es nuevo? Dalo de alta con el botón +: recibirá su acceso al portal por correo.')
                ->createOptionForm([...PersonFields::make(phoneRequired: true), PersonFields::nit()])
                ->createOptionModalHeading('Nuevo paciente')
                ->createOptionUsing(fn (array $data): string => DB::transaction(fn (): string => app(DatabasePatientRegistry::class)->findOrRegister(
                    $data['name'], $data['email'], (string) $data['phone_e164'], $data['nit'] ?? 'CF',
                )))
                ->createOptionAction(fn (Action $action): Action => $action->visible(DomainCommand::userCan('patient.create'))),
            Select::make('service_id')->label('Servicio')
                ->options(fn (): array => Service::query()->where('is_active', true)->pluck('name', 'id')->all())
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    $set('starts_at', null);
                    $this->disabledDates = self::datesWithoutTimes($state);
                })
                ->createOptionForm(ServiceResource::formFields())
                ->createOptionModalHeading('Nuevo servicio')
                ->createOptionUsing(fn (array $data): string => Service::register($data)->id)
                ->createOptionAction(fn (Action $action): Action => $action->visible(DomainCommand::userCan('settings.manage'))),
            DatePicker::make('date')->label('Fecha')
                ->minDate(now(config('clinic.timezone'))->startOfDay())
                ->disabledDates(fn (): array => $this->disabledDates)
                ->native(false)
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('starts_at', null)),
            Select::make('starts_at')->label('Hora')
                ->options(fn (Get $get): array => SlotOptions::for($get('service_id'), $get('date'), assisted: true))
                ->placeholder(fn (Get $get): string => $get('service_id') && $get('date') && SlotOptions::for($get('service_id'), $get('date'), assisted: true) === []
                    ? 'Sin horarios libres ese día'
                    : __('filament-forms::components.select.placeholder'))
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

    /** @return list<string> fechas `Y-m-d` que el calendario no deja elegir */
    private static function datesWithoutTimes(?string $serviceId): array
    {
        if ($serviceId === null) {
            return [];
        }

        $today = now(config('clinic.timezone'))->startOfDay();

        // ponytail: solo los proximos 60 dias; mas alla avisa el campo Hora. Ampliar si recepcion agenda mas lejos.
        return collect(range(0, 59))
            ->map(fn (int $offset): string => $today->copy()->addDays($offset)->toDateString())
            ->filter(fn (string $date): bool => SlotOptions::for($serviceId, $date, assisted: true) === [])
            ->values()
            ->all();
    }
}
