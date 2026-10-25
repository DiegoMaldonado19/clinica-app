<?php

namespace App\Filament\Resources\Patients\Tables;

use App\Models\Patient;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->state(fn (Patient $record): ?string => $record->user->getFilamentAvatarUrl())
                    ->circular(),
                TextColumn::make('user.name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Correo')
                    ->searchable(),
                TextColumn::make('document_number')
                    ->label('Documento')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Nacimiento')
                    ->date()
                    ->sortable(),
            ])
            // El expediente no se borra (doc 06 §7).
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                ]),
            ]);
    }
}
