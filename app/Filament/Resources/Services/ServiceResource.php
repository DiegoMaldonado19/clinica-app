<?php

namespace App\Filament\Resources\Services;

use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Models\Service;
use App\Models\ServiceCategory;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/** MU-15: servicios y tarifas. Una tarifa nueva no toca las citas ya solicitadas. */
class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = 'servicio';

    protected static ?string $pluralModelLabel = 'servicios y tarifas';

    protected static ?string $navigationLabel = 'Servicios y tarifas';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('prices');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::formFields());
    }

    /**
     * Tambien los usa el alta rapida de servicio al agendar. La tarifa inicial
     * solo se pide al crear; despues se programa con vigencia (EditService).
     *
     * @return list<Component>
     */
    public static function formFields(): array
    {
        return [
            TextInput::make('name')->label('Nombre')->maxLength(120)->required(),
            Select::make('category_id')->label('Categoría')
                ->options(fn (): array => ServiceCategory::query()->pluck('label', 'id')->all())
                ->required()
                ->createOptionForm([
                    TextInput::make('label')->label('Nombre de la categoría')->maxLength(40)->required()
                        ->unique('service_categories', 'label'),
                ])
                ->createOptionModalHeading('Nueva categoría')
                ->createOptionUsing(fn (array $data): int => ServiceCategory::create([
                    'code' => Str::upper(Str::slug($data['label'], '_')),
                    'label' => $data['label'],
                ])->id),
            TextInput::make('duration_minutes')->label('Duración (minutos)')->numeric()->minValue(15)->maxValue(60)->required()
                ->helperText('Los horarios se ofrecen cada hora: la sesión debe caber en 60 minutos.'),
            TextInput::make('initial_price')->label('Tarifa (Q)')->numeric()->minValue(1)->required()->hiddenOn('edit'),
            Toggle::make('is_active')->label('Se ofrece en la landing')->default(true),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Servicio'),
                TextColumn::make('duration_minutes')->label('Duración')->suffix(' min'),
                TextColumn::make('current_price')->label('Tarifa vigente')
                    ->state(fn (Service $record): string => $record->currentPrice()?->format() ?? '—'),
                IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServices::route('/'),
            'create' => CreateService::route('/create'),
            'edit' => EditService::route('/{record}/edit'),
        ];
    }
}
