<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Filament\Pages\SessionNote;
use App\Filament\Support\DomainCommand;
use App\Models\Appointment;
use App\Scheduling\Application\AppointmentTransitions;
use App\Shared\Infrastructure\Audit\AuditLog;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Las transiciones de la cita como acciones de Filament. Cada una comprueba su
 * permiso atomico y delega en AppointmentTransitions: aqui no se decide nada.
 */
final class AppointmentActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Aprobar')
            ->icon(Heroicon::OutlinedCheck)
            ->color('primary')
            ->visible(fn (Appointment $record): bool => $record->statusCode() === 'SOLICITADA' && self::can('appointment.approve'))
            ->action(fn (Appointment $record) => self::run(
                fn (AppointmentTransitions $t) => $t->approve($record->id, self::userId()),
                'Cita aprobada. Se envió el enlace de pago al paciente.',
            ));
    }

    public static function approveSelected(): BulkAction
    {
        return BulkAction::make('approveSelected')
            ->label('Aprobar seleccionadas')
            ->icon(Heroicon::OutlinedCheck)
            ->requiresConfirmation()
            ->visible(fn (): bool => self::can('appointment.approve'))
            ->action(fn (Collection $records) => self::run(function (AppointmentTransitions $t) use ($records) {
                foreach ($records as $appointment) {
                    $t->approve($appointment->getKey(), self::userId());
                }
            }, 'Citas aprobadas. Se envió el enlace de pago a cada paciente.'));
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Rechazar')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn (Appointment $record): bool => $record->statusCode() === 'SOLICITADA' && self::can('appointment.reject'))
            ->schema([
                Textarea::make('reason')->label('Motivo (lo verá el paciente)')->required()->maxLength(500),
            ])
            ->action(fn (Appointment $record, array $data) => self::run(
                fn (AppointmentTransitions $t) => $t->reject($record->id, self::userId(), $data['reason']),
                'Solicitud rechazada. El paciente fue notificado.',
            ));
    }

    public static function checkIn(): Action
    {
        return Action::make('checkIn')
            ->label(fn (Appointment $record): string => $record->statusCode() === 'PAGO_EN_CAJA' ? 'Cobrar y registrar llegada' : 'Registrar llegada')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Appointment $record): bool => in_array($record->statusCode(), ['AGENDADA', 'PAGO_EN_CAJA'], true) && self::can('appointment.checkin'))
            ->action(fn (Appointment $record) => self::run(
                fn (AppointmentTransitions $t) => $t->checkIn($record->id, self::userId()),
                'Llegada registrada.',
            ));
    }

    public static function markNoShow(): Action
    {
        return Action::make('markNoShow')
            ->label('Marcar inasistencia')
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Se cobra el 100 % de la tarifa y el horario no se libera.')
            ->visible(fn (Appointment $record): bool => $record->statusCode() === 'AGENDADA' && $record->starts_at->isPast() && self::can('appointment.mark_no_show'))
            ->action(fn (Appointment $record) => self::run(function (AppointmentTransitions $t) use ($record) {
                $t->markNoShow($record->id, self::userId());
                AuditLog::record('appointment.no_show', 'appointment', $record->id);
            }, 'Inasistencia registrada.'));
    }

    /** RF-24: el cargo exacto antes de confirmar (MSG-21). */
    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancelar a solicitud del paciente')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(fn (Appointment $record): string => self::cancellationPreview($record))
            ->visible(fn (Appointment $record): bool => in_array($record->statusCode(), ['CONFIRMADA_PENDIENTE_PAGO', 'PAGO_EN_CAJA', 'AGENDADA'], true) && self::can('appointment.cancel.any'))
            ->action(fn (Appointment $record) => self::run(function (AppointmentTransitions $t) use ($record) {
                $outcome = $t->cancel($record->id, self::userId());
                AuditLog::record('appointment.cancelled', 'appointment', $record->id, ['fee_percentage' => $outcome->feePercentage]);
            }, 'Cita cancelada.'));
    }

    /** RN-18: la indisponibilidad de la psicologa nunca le cuesta al paciente. */
    public static function cancelByTherapist(): Action
    {
        return Action::make('cancelByTherapist')
            ->label('Cancelar por indisponibilidad')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Se cancela sin cargo; si ya pagó, el monto queda como crédito. El paciente recibe un aviso.')
            ->visible(fn (Appointment $record): bool => in_array($record->statusCode(), ['SOLICITADA', 'CONFIRMADA_PENDIENTE_PAGO', 'PAGO_EN_CAJA', 'AGENDADA'], true) && self::can('schedule.block'))
            ->action(fn (Appointment $record) => self::run(function (AppointmentTransitions $t) use ($record) {
                $t->cancelByTherapist($record->id, self::userId());
                AuditLog::record('appointment.cancelled', 'appointment', $record->id, ['by_therapist' => true]);
            }, 'Cita cancelada sin cargo.'));
    }

    public static function documentSession(): Action
    {
        return Action::make('documentSession')
            ->label('Nota de la sesión')
            ->icon(Heroicon::OutlinedDocumentText)
            ->visible(fn (Appointment $record): bool => in_array($record->statusCode(), ['EN_CURSO', 'ATENDIDA'], true) && self::can('clinical_note.view'))
            ->url(fn (Appointment $record): string => SessionNote::getUrl(['appointment' => $record->id]));
    }

    public static function cancellationPreview(Appointment $record): string
    {
        $outcome = app(AppointmentTransitions::class)->previewCancellation($record->id);

        if (! $outcome->feeApplies) {
            return 'Esta cancelación no tiene cargo. Si la cita estaba pagada, el monto queda como crédito a favor.';
        }

        $notice = now()->diffForHumans($record->starts_at, ['syntax' => CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2]);

        // MSG-21
        return "Estás cancelando con {$notice} de anticipación. Según la política aceptada, se aplicará un cargo de "
            ."{$outcome->feeAmount->format()} ({$outcome->feePercentage} % de la tarifa). ¿Confirmas?";
    }

    /** @param callable(AppointmentTransitions): mixed $command */
    public static function run(callable $command, string $success): void
    {
        DomainCommand::run(fn () => $command(app(AppointmentTransitions::class)), $success);
    }

    private static function can(string $ability): bool
    {
        return DomainCommand::userCan($ability);
    }

    private static function userId(): string
    {
        return (string) auth()->id();
    }
}
