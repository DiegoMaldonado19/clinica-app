<?php

namespace App\Filament\Resources\PsychTestAssignments;

use App\ClinicalRecords\Domain\PsychTest;
use App\Filament\Resources\PsychTestAssignments\Pages\ListPsychTestAssignments;
use App\Filament\Resources\PsychTestAssignments\Pages\ViewPsychTestAssignment;
use App\Filament\Support\StatusBadge;
use App\Models\PsychTestAssignment;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * HU-13. La lista no muestra resultados: verlos exige abrir la prueba, y cada
 * apertura queda en bitacora (RN-12).
 */
class PsychTestAssignmentResource extends Resource
{
    protected static ?string $model = PsychTestAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $modelLabel = 'prueba psicométrica';

    protected static ?string $pluralModelLabel = 'pruebas psicométricas';

    protected static ?string $navigationLabel = 'Pruebas psicométricas';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('record.patient:id,name');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('record.patient.name')->label('Paciente')->searchable(),
                TextColumn::make('test')->label('Prueba')->formatStateUsing(fn (PsychTest $state): string => $state->value),
                // date() formatea en UTC: sin la zona de la clinica, el limite de las 23:59 aparece con el dia siguiente.
                TextColumn::make('created_at')->label('Asignada')->date('j M Y', config('clinic.timezone')),
                TextColumn::make('due_at')->label('Fecha límite')->date('j M Y', config('clinic.timezone')),
                self::statusColumn(),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver resultado')
                    ->visible(fn (PsychTestAssignment $record): bool => $record->submitted_at !== null),
            ])
            ->emptyStateHeading('Todavía no hay pruebas asignadas');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Resultado')->columns(2)->schema([
                TextEntry::make('record.patient.name')->label('Paciente'),
                TextEntry::make('test')->label('Prueba')->formatStateUsing(fn (PsychTest $state): string => $state->label()),
                TextEntry::make('submitted_at')->label('Respondida')->dateTime('j \d\e F Y, H:i')->placeholder('Sin responder'),
                TextEntry::make('score')->label('Puntuación')->placeholder('Sin responder')
                    ->state(fn (PsychTestAssignment $record): ?string => ($result = $record->result())
                        ? "{$result->score} de {$record->test->maxScore()} · {$result->severity}"
                        : null),
                TextEntry::make('urgent')->label('Revisión urgente')->columnSpanFull()
                    ->state('El ítem 9 es mayor que 0: pensamientos de muerte o de hacerse daño.')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->visible(fn (PsychTestAssignment $record): bool => (bool) $record->result()?->urgent),
            ]),
            Section::make('Respuestas')
                ->visible(fn (PsychTestAssignment $record): bool => $record->submitted_at !== null)
                ->schema([
                    TextEntry::make('answer_list')->hiddenLabel()->listWithLineBreaks()->bulleted()
                        ->state(fn (PsychTestAssignment $record): array => array_map(
                            fn (string $item, int $answer): string => "{$item}: ".PsychTest::OPTIONS[$answer],
                            $record->test->items(),
                            $record->answerList() ?? [],
                        )),
                ]),
        ]);
    }

    /** Columna de estado compartida con «Mis pruebas» del portal. */
    public static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')->label('Estado')->badge()
            ->state(fn (PsychTestAssignment $record): string => $record->statusCode())
            ->formatStateUsing(fn (string $state): string => ucfirst(strtolower($state)))
            ->color(fn (string $state): string => StatusBadge::color($state))
            ->icon(fn (string $state): Heroicon => StatusBadge::icon($state));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPsychTestAssignments::route('/'),
            'view' => ViewPsychTestAssignment::route('/{record}'),
        ];
    }
}
