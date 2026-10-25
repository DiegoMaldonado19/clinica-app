<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Support\DomainCommand;
use App\Identity\Infrastructure\TemporaryPassword;
use App\Models\User;
use App\Shared\Infrastructure\Audit\AuditLog;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Lo que se hace con una cuenta desde el listado, sin entrar a editarla. Nunca
 * sobre la propia: la contraseña propia se cambia en el perfil.
 */
final class UserActions
{
    public static function password(): Action
    {
        return Action::make('password')
            ->label(fn (User $record): string => $record->must_change_password ? 'Generar contraseña temporal' : 'Cambiar contraseña')
            ->icon(Heroicon::OutlinedKey)
            ->authorize('update')
            ->hidden(fn (User $record): bool => $record->is(auth()->user()))
            ->schema([UserForm::passwordField()->required()])
            ->modalSubmitActionLabel('Guardar')
            ->action(fn (User $record, array $data) => DomainCommand::run(function () use ($record, $data) {
                $record->update(app(TemporaryPassword::class)->attributes($data['password']));
                AuditLog::record('user.password_reset', 'user', (string) $record->getKey());
            }, 'Contraseña guardada. Compártela con la persona: deberá cambiarla al entrar.'));
    }

    /** La baja de un usuario es logica (doc 06 §7). */
    public static function toggleActive(): Action
    {
        return Action::make('toggleActive')
            ->label(fn (User $record): string => $record->is_active ? 'Desactivar' : 'Activar')
            ->icon(fn (User $record): Heroicon => $record->is_active ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
            ->color(fn (User $record): string => $record->is_active ? 'danger' : 'success')
            ->authorize('update')
            ->hidden(fn (User $record): bool => $record->is(auth()->user()))
            ->requiresConfirmation()
            ->action(fn (User $record) => DomainCommand::run(function () use ($record) {
                $record->update(['is_active' => ! $record->is_active]);
                AuditLog::record($record->is_active ? 'user.activated' : 'user.deactivated', 'user', (string) $record->getKey());
            }, $record->is_active ? 'Cuenta desactivada.' : 'Cuenta activada.'));
    }
}
