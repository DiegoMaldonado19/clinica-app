<?php

declare(strict_types=1);

namespace App\Filament\Portal\Pages;

use App\Billing\Application\BillingService;
use App\Billing\Domain\DuplicateReceipt;
use App\Billing\Domain\PaymentProof;
use App\Filament\Actions\AppointmentActions;
use App\Filament\Support\StatusBadge;
use App\Models\Appointment;
use App\Models\Bank;
use App\Scheduling\Application\AppointmentTransitions;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Infrastructure\Audit\AuditLog;
use App\Support\PolicyText;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * P-01 y P-02: mis citas, mi pago y cancelar con el cargo a la vista. Sin
 * ningun contenido clinico. Todas las consultas se limitan al paciente en
 * sesion (RN-13).
 */
class MyAppointments extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Mis citas';

    protected static ?string $title = 'Mis citas';

    protected static ?string $slug = 'mis-citas';

    protected string $view = 'filament.portal.my-appointments';

    /** La cita con pago pendiente mas proxima, para la cuenta regresiva de P-01. */
    public function pendingPayment(): ?Appointment
    {
        return $this->mine()->inStatus(['CONFIRMADA_PENDIENTE_PAGO'])->with('service:id,name')->orderBy('starts_at')->first();
    }

    public function creditBalance(): ?string
    {
        $cents = (int) DB::table('patient_credits')->where('patient_id', auth()->id())->whereNull('consumed_at')->sum('amount_cents');

        return $cents > 0 ? Money::gtq($cents)->format() : null;
    }

    public function policy(): PolicyText
    {
        return app(PolicyText::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->mine()->with(['service:id,name', 'status:id,code,label']))
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('starts_at')->label('Cita')->dateTime('l j \d\e F · H:i'),
                TextColumn::make('service.name')->label('Servicio'),
                TextColumn::make('status.label')->label('Estado')->badge()
                    ->formatStateUsing(fn (Appointment $record): string => self::statusLabel($record->statusCode()))
                    ->color(fn (Appointment $record): string => StatusBadge::color($record->statusCode()))
                    ->icon(fn (Appointment $record): Heroicon => StatusBadge::icon($record->statusCode())),
                TextColumn::make('fee_amount_cents')->label('Monto')->formatStateUsing(fn (int $state): string => Money::gtq($state)->format()),
            ])
            ->recordActions([
                $this->submitProofAction(),
                Action::make('payAtDesk')
                    ->label('Pagar en efectivo al llegar')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Appointment $record): bool => $record->statusCode() === 'CONFIRMADA_PENDIENTE_PAGO')
                    ->action(fn (Appointment $record) => AppointmentActions::run(
                        fn (AppointmentTransitions $t) => $t->payAtDesk($this->ownedId($record)),
                        'Perfecto. Recuerda llegar al menos 15 minutos antes para realizar el pago en recepción.',
                    )),
                Action::make('cancel')
                    ->label('Cancelar')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Appointment $record): string => AppointmentActions::cancellationPreview($record))
                    ->visible(fn (Appointment $record): bool => in_array($record->statusCode(), ['CONFIRMADA_PENDIENTE_PAGO', 'PAGO_EN_CAJA', 'AGENDADA'], true)
                        && (bool) auth()->user()?->hasAbility('appointment.cancel.own'))
                    ->action(fn (Appointment $record) => AppointmentActions::run(function (AppointmentTransitions $t) use ($record) {
                        $outcome = $t->cancel($this->ownedId($record), (string) auth()->id());
                        AuditLog::record('appointment.cancelled', 'appointment', $record->id, ['fee_percentage' => $outcome->feePercentage]);
                    }, 'Tu cita fue cancelada. Te enviamos el detalle por correo.')),
            ])
            ->emptyStateHeading('Todavía no tienes citas')
            ->emptyStateDescription('Puedes agendar desde la página de inicio.');
    }

    private function submitProofAction(): Action
    {
        return Action::make('submitProof')
            ->label('Registrar mi pago')
            ->visible(fn (Appointment $record): bool => $record->statusCode() === 'CONFIRMADA_PENDIENTE_PAGO'
                && (bool) auth()->user()?->hasAbility('payment.submit'))
            ->schema([
                TextInput::make('receipt_number')->label('Número de boleta')->required()->maxLength(50),
                Select::make('bank_id')->label('Banco de origen')
                    ->options(fn (): array => Bank::query()->where('is_active', true)->pluck('label', 'id')->all())
                    ->required(),
                TextInput::make('account_last_digits')->label('Últimos 4 dígitos de tu cuenta')
                    ->helperText('Solo guardamos estos 4 dígitos.')
                    ->required()->length(4)->regex('/^\d{4}$/'),
                DatePicker::make('deposited_on')->label('Fecha del depósito')->maxDate(now(config('clinic.timezone')))->required()
                    ->validationMessages(['before_or_equal' => 'La fecha del depósito no puede ser posterior a hoy.']),
                FileUpload::make('file')->label('Comprobante (PDF)')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(5 * 1024)
                    ->storeFiles(false)
                    ->required()
                    ->validationMessages([
                        'mimetypes' => 'El comprobante debe ser un archivo PDF. Si tienes una foto, descárgalo desde la app de tu banco.',
                        'max' => 'El máximo permitido es 5 MB.',
                    ]),
            ])
            ->action(fn (Appointment $record, array $data) => $this->submitProof($record->id, $data));
    }

    /**
     * Publico para Livewire, asi que el navegador tambien puede invocarlo: la
     * cita se vuelve a filtrar por dueno y el tipo se comprueba por el contenido
     * real del archivo, no por la extension (doc 05 §6).
     *
     * @param  array<string, mixed>  $data
     */
    public function submitProof(string $appointmentId, array $data): void
    {
        $appointmentId = $this->mine()->whereKey($appointmentId)->firstOrFail()->id;
        $file = $data['file'] ?? null;

        if (! $file instanceof UploadedFile || (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) !== 'application/pdf') {
            Notification::make()->danger()->title('El comprobante debe ser un archivo PDF. Si tienes una foto, descárgalo desde la app de tu banco.')->send();

            return;
        }

        $path = 'comprobantes/'.Str::uuid7().'.pdf';

        try {
            DB::transaction(function () use ($appointmentId, $data, $file, $path) {
                Storage::disk('s3')->putFileAs('comprobantes', $file, basename($path));

                app(BillingService::class)->submitProof($appointmentId, new PaymentProof(
                    trim((string) $data['receipt_number']),
                    (int) $data['bank_id'],
                    (string) $data['account_last_digits'],
                    new \DateTimeImmutable((string) $data['deposited_on']),
                    $path,
                    (string) hash_file('sha256', $file->getRealPath()),
                    (int) $file->getSize(),
                ));
            });
        } catch (DuplicateReceipt|DomainException $exception) {
            Storage::disk('s3')->delete($path);
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title('Recibimos tu comprobante. Lo revisaremos y te avisaremos apenas quede confirmado.')->send();
    }

    /** @return Builder<Appointment> */
    private function mine(): Builder
    {
        return Appointment::query()->where('patient_id', auth()->id());
    }

    /** Nunca se confia en el id que llega del navegador: se vuelve a filtrar por dueno. */
    private function ownedId(Appointment $record): string
    {
        return $this->mine()->whereKey($record->getKey())->firstOrFail()->id;
    }

    /** Textos de P-02 para el paciente: sin jerga interna como "SLA" o "HOLD". */
    private static function statusLabel(string $code): string
    {
        return match ($code) {
            'SOLICITADA' => 'En revisión',
            'CONFIRMADA_PENDIENTE_PAGO' => 'Pago pendiente',
            'PAGO_EN_REVISION' => 'Revisando tu pago',
            'PAGO_EN_CAJA' => 'Pagas al llegar',
            'AGENDADA' => 'Confirmada',
            'EN_CURSO' => 'En sesión',
            'ATENDIDA' => 'Atendida',
            'EXPIRADA' => 'Venció sin respuesta',
            'RECHAZADA' => 'No confirmada',
            'NO_SHOW' => 'No asististe',
            default => 'Cancelada',
        };
    }
}
