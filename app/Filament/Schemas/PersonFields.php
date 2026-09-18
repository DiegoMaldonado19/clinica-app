<?php

declare(strict_types=1);

namespace App\Filament\Schemas;

use App\Shared\Domain\ValueObject\Nit;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Support\DomainRule;
use Closure;
use Filament\Forms\Components\TextInput;

/**
 * Los datos de una persona, definidos una vez: los usan el alta de usuarios,
 * la ficha del paciente y el alta rapida desde un desplegable.
 *
 * Las reglas van envueltas en `fn (): Closure`: una Closure suelta en `rule()`
 * la evalua Filament como callback propio, no como regla de Laravel.
 */
final class PersonFields
{
    /** @return list<TextInput> nombre, correo y telefono de `users` */
    public static function make(bool $phoneRequired = false): array
    {
        return [
            TextInput::make('name')
                ->label('Nombre')
                ->maxLength(150)
                ->required(),
            TextInput::make('email')
                ->label('Correo')
                ->email()
                ->maxLength(190)
                ->unique('users', 'email', ignoreRecord: true)
                ->validationMessages(['unique' => 'Ese correo ya está registrado: búscalo en la lista.'])
                ->required(),
            TextInput::make('phone_e164')
                ->label('Teléfono')
                ->tel()
                ->maxLength(20)
                ->required($phoneRequired)
                ->rule(fn (): Closure => DomainRule::from(fn (string $value) => $value === '' ? null : PhoneNumber::fromGuatemalan($value)))
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? PhoneNumber::fromGuatemalan($state)->e164 : null),
        ];
    }

    public static function nit(): TextInput
    {
        return TextInput::make('nit')
            ->label('NIT')
            ->placeholder('CF')
            ->helperText('Si no tiene NIT, escribe CF.')
            ->maxLength(20)
            ->rule(fn (): Closure => DomainRule::from(fn (string $value) => $value === '' ? null : Nit::from($value)))
            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Nit::from($state)->value : null);
    }
}
