<?php

namespace App\Filament\Resources\AuditLogEntries;

use App\Filament\Resources\AuditLogEntries\Pages\ListAuditLogEntries;
use App\Models\AuditLogEntry;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** MU-17 · HU-18: quien accedio a un expediente, cuando y desde donde. Solo lectura. */
class AuditLogEntryResource extends Resource
{
    protected static ?string $model = AuditLogEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $modelLabel = 'registro de bitácora';

    protected static ?string $pluralModelLabel = 'bitácora';

    protected static ?string $navigationLabel = 'Bitácora';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['actor:id,name', 'subjectUser:id,name']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')->label('Cuándo')->dateTime('d/m/Y H:i:s'),
                TextColumn::make('actor.name')->label('Quién')->placeholder('El sistema'),
                TextColumn::make('action')->label('Acción')->badge()
                    ->color(fn (string $state): string => str_ends_with($state, 'denied') || str_ends_with($state, 'failed') ? 'danger' : 'gray'),
                TextColumn::make('subject_type')->label('Sobre')->placeholder('—'),
                TextColumn::make('subject_name')->label('Paciente / registro')
                    ->state(fn (AuditLogEntry $record): ?string => $record->subjectUser->name ?? $record->subject_id),
                TextColumn::make('ip')->label('IP')->state(fn (AuditLogEntry $record): ?string => $record->ipAddress())->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('action')->label('Acción')
                    ->options(fn (): array => AuditLogEntry::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
                SelectFilter::make('subject_id')->label('Expediente de')
                    ->options(fn (): array => User::query()->whereRelation('role', 'code', 'patient')->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogEntries::route('/')];
    }
}
