<?php

namespace App\Filament\Resources\Faqs;

use App\Filament\Resources\Faqs\Pages\ManageFaqs;
use App\Models\Faq;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** HU-24 · RF-63: lo que responde la landing y lo que cita el asistente. */
class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $modelLabel = 'pregunta frecuente';

    protected static ?string $pluralModelLabel = 'preguntas frecuentes';

    protected static ?string $navigationLabel = 'Preguntas frecuentes';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('question')->label('Pregunta')->maxLength(200)->required()->columnSpanFull(),
            Textarea::make('answer')->label('Respuesta')->rows(5)->maxLength(2000)->required()->columnSpanFull()
                ->helperText('La ve el público en la landing y el asistente la cita como fuente. Sin datos de pacientes.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('question')->label('Pregunta')->wrap(),
                TextColumn::make('answer')->label('Respuesta')->limit(90)->wrap(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('Todavía no hay preguntas frecuentes');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFaqs::route('/'),
        ];
    }
}
