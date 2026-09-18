<?php

namespace App\Filament\Resources\Payments;

use App\Billing\Application\BillingService;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Support\DomainCommand;
use App\Filament\Support\StatusBadge;
use App\Models\Payment;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Infrastructure\Audit\AuditLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * D-03 · HU-07. Datos capturados y comprobante lado a lado. La aprobacion es
 * siempre humana (RN-09): el sistema valida, no decide.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'pago';

    protected static ?string $pluralModelLabel = 'pagos';

    protected static ?string $navigationLabel = 'Conciliación de pagos';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'status:id,code,label', 'appointment:id,patient_id,service_id,starts_at',
            'appointment.patient:id,name', 'appointment.service:id,name', 'latestProof.bank:id,label',
        ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $inReview = Payment::query()->whereRelation('status', 'code', 'EN_REVISION')->count();

        return $inReview > 0 ? (string) $inReview : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('appointment.patient.name')->label('Paciente')->searchable(),
                TextColumn::make('appointment.starts_at')->label('Cita')->dateTime('D j M · H:i'),
                TextColumn::make('kind')->label('Concepto')->formatStateUsing(fn (string $state): string => self::kindLabel($state)),
                TextColumn::make('amount_cents')->label('Monto')->formatStateUsing(fn (int $state): string => Money::gtq($state)->format()),
                TextColumn::make('status.label')->label('Estado')->badge()
                    ->color(fn (Payment $record): string => StatusBadge::color($record->status->code))
                    ->icon(fn (Payment $record): Heroicon => StatusBadge::icon($record->status->code)),
                TextColumn::make('submitted_at')->label('Subido')->dateTime('j M · H:i')->placeholder('—'),
            ])
            ->recordActions([ViewAction::make()->label('Conciliar')]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(5)->columnSpanFull()->schema([
                Section::make('Datos capturados')->columnSpan(2)->schema([
                    TextEntry::make('appointment.patient.name')->label('Paciente'),
                    TextEntry::make('appointment.service.name')->label('Servicio'),
                    TextEntry::make('appointment.starts_at')->label('Cita')->dateTime('l j \d\e F · H:i'),
                    TextEntry::make('amount_cents')->label('Monto esperado')->formatStateUsing(fn (int $state): string => Money::gtq($state)->format()),
                    TextEntry::make('kind')->label('Concepto')->formatStateUsing(fn (string $state): string => self::kindLabel($state)),
                    TextEntry::make('latestProof.receipt_number')->label('No. de boleta')->placeholder('—'),
                    TextEntry::make('latestProof.bank.label')->label('Banco')->placeholder('—'),
                    TextEntry::make('latestProof.origin_account_mask')->label('Cuenta origen')->placeholder('—'),
                    TextEntry::make('latestProof.deposited_on')->label('Fecha de depósito')->date('j M Y')->placeholder('—'),
                    TextEntry::make('latestProof.uploaded_at')->label('Subido')->dateTime('j M · H:i')->placeholder('—'),
                    TextEntry::make('rejection_reason')->label('Motivo del último rechazo')->placeholder('—'),
                    TextEntry::make('waiver_reason')->label('Motivo de la exoneración')->placeholder('—'),
                    // Lo que el sistema ya verifico; lo que queda es juicio humano.
                    IconEntry::make('checks.format')->label('Formato de archivo válido (PDF por contenido)')->boolean()
                        ->state(fn (Payment $record): bool => $record->latestProof !== null),
                    IconEntry::make('checks.receipt')->label('Número de boleta no duplicado')->boolean()
                        ->state(fn (Payment $record): bool => $record->latestProof !== null),
                    IconEntry::make('checks.date')->label('Fecha de depósito no futura')->boolean()
                        ->state(fn (Payment $record): bool => $record->latestProof?->deposited_on?->lte(now()) ?? false),
                ]),
                Section::make('Comprobante')->columnSpan(3)->schema([
                    ViewEntry::make('proof')->hiddenLabel()->view('filament.proof-viewer'),
                ]),
            ]),
        ]);
    }

    /** @return list<Action> */
    public static function reviewActions(): array
    {
        return [
            Action::make('approve')
                ->label('Aprobar pago')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Confirma que el monto y el destinatario del comprobante corresponden.')
                ->visible(fn (Payment $record): bool => $record->status->code === 'EN_REVISION' && DomainCommand::userCan('payment.approve'))
                ->action(fn (Payment $record) => DomainCommand::run(function () use ($record) {
                    app(BillingService::class)->approve($record->id, (string) auth()->id());
                    AuditLog::record('payment.approved', 'payment', $record->id);
                }, 'Pago aprobado. La cita quedó agendada.')),
            Action::make('reject')
                ->label('Rechazar')
                ->color('danger')
                ->visible(fn (Payment $record): bool => $record->status->code === 'EN_REVISION' && DomainCommand::userCan('payment.approve'))
                ->schema([Textarea::make('reason')->label('Motivo (lo verá el paciente)')->required()->maxLength(500)])
                ->action(fn (Payment $record, array $data) => DomainCommand::run(function () use ($record, $data) {
                    app(BillingService::class)->reject($record->id, (string) auth()->id(), $data['reason']);
                    AuditLog::record('payment.rejected', 'payment', $record->id, ['reason' => $data['reason']]);
                }, 'Comprobante rechazado. El paciente puede subir otro.')),
            Action::make('waive')
                ->label('Exonerar cargo')
                ->color('gray')
                ->visible(fn (Payment $record): bool => $record->kind !== 'SESSION_FEE'
                    && in_array($record->status->code, ['PENDIENTE', 'APROBADO'], true)
                    && DomainCommand::userCan('payment.waive_fee'))
                ->schema([Textarea::make('reason')->label('Motivo de la exoneración')->required()->maxLength(500)])
                ->action(fn (Payment $record, array $data) => DomainCommand::run(function () use ($record, $data) {
                    app(BillingService::class)->waive($record->id, (string) auth()->id(), $data['reason']);
                    AuditLog::record('late_fee.waived', 'payment', $record->id, ['reason' => $data['reason']]);
                }, 'Cargo exonerado.')),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }

    private static function kindLabel(string $kind): string
    {
        return match ($kind) {
            'SESSION_FEE' => 'Tarifa de la sesión',
            'LATE_CANCELLATION_FEE' => 'Cargo por cancelación tardía',
            default => 'Cargo por inasistencia',
        };
    }
}
