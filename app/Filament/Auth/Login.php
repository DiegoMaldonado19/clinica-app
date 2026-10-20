<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Shared\Infrastructure\Audit\AuditLog;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Doc 05 §5.1: `two_factor.failed`. Filament no emite ningun evento cuando el
 * segundo factor falla; esta pagina lo registra y vuelve a lanzar la misma
 * excepcion, sin tocar la autenticacion.
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        try {
            return parent::authenticate();
        } catch (ValidationException $exception) {
            $failedField = collect(array_keys($exception->errors()))
                ->first(fn (string $field): bool => str_starts_with($field, 'data.multiFactor.'));

            if ($failedField !== null) {
                AuditLog::record('two_factor.failed', 'user', (string) decrypt($this->userUndertakingMultiFactorAuthentication), [
                    'factor' => Str::between($failedField, 'data.multiFactor.', '.'),
                ]);
            }

            throw $exception;
        }
    }
}
