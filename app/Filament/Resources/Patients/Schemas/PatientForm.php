<?php

namespace App\Filament\Resources\Patients\Schemas;

use App\Models\Patient;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class PatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // La ficha extiende una cuenta ya existente: es la relacion
                // 1-0..1 del ERD. La cuenta se da de alta en Usuarios.
                Select::make('id')
                    ->label('Cuenta')
                    ->options(fn (?Patient $record): array => User::query()
                        ->whereRelation('role', 'code', 'patient')
                        ->when(
                            $record === null,
                            fn ($query) => $query->whereNotIn('id', Patient::query()->select('id')),
                        )
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required()
                    ->disabledOn('edit'),
                Select::make('document_type_id')
                    ->label('Tipo de documento')
                    ->options(fn (): array => self::catalog('document_types')),
                TextInput::make('document_number')
                    ->label('Numero de documento')
                    ->maxLength(30),
                Select::make('sex_id')
                    ->label('Sexo')
                    ->options(fn (): array => self::catalog('sexes')),
                DatePicker::make('birth_date')
                    ->label('Fecha de nacimiento')
                    ->maxDate(now()),
                TextInput::make('emergency_contact_name')
                    ->label('Contacto de emergencia')
                    ->maxLength(150),
                TextInput::make('emergency_contact_phone_e164')
                    ->label('Telefono de emergencia')
                    ->tel()
                    ->maxLength(20),
                TextInput::make('nit')
                    ->label('NIT')
                    ->maxLength(20),
                // Ficha administrativa (doc 05 §4.3): recepcion la ve y la edita.
                // El contenido clinico nunca aparece aqui.
                Section::make('Ficha administrativa')
                    ->relationship('clinicalRecord')
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => $data + ['opened_at' => now()])
                    ->visible(fn (): bool => (bool) auth()->user()?->hasAbility('clinical_intake.view'))
                    ->disabled(fn (): bool => ! auth()->user()?->hasAbility('clinical_intake.create'))
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('reported_reason')->label('Motivo de consulta reportado'),
                        TextInput::make('referred_by')->label('Referido por')->maxLength(150),
                    ]),
            ]);
    }

    /** @return array<int, string> */
    private static function catalog(string $table): array
    {
        return DB::table($table)->where('is_active', true)->pluck('label', 'id')->all();
    }
}
