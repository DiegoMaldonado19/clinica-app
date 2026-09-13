<?php

namespace App\Filament\Resources\Patients\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
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
                EditAction::make(),
            ]);
    }
}
