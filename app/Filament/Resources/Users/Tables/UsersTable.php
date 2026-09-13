<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),
                TextColumn::make('role.label')
                    ->label('Rol')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                IconColumn::make('must_change_password')
                    ->label('Debe cambiar contrasena')
                    ->boolean(),
                TextColumn::make('last_login_at')
                    ->label('Ultimo ingreso')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role_id')
                    ->label('Rol')
                    ->relationship('role', 'label'),
            ])
            // Sin acciones de borrado: la baja de un usuario es logica (`is_active`).
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
