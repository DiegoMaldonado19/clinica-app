<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Schemas\PersonFields;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('role_id')
                    ->label('Rol')
                    ->relationship('role', 'label')
                    ->required(),
                ...PersonFields::make(),
                // En el alta se genera una credencial temporal (RN-15); al editar,
                // dejarla vacia conserva la actual.
                TextInput::make('password')
                    ->label('Contrasena temporal')
                    ->password()
                    ->revealable()
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create'),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true),
            ]);
    }
}
