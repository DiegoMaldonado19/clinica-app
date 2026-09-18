<?php

namespace App\Filament\Resources\ScheduleBlocks;

use App\Filament\Resources\ScheduleBlocks\Pages\CreateScheduleBlock;
use App\Filament\Resources\ScheduleBlocks\Pages\ListScheduleBlocks;
use App\Models\ScheduleBlock;
use App\Scheduling\Infrastructure\Persistence\DatabaseAvailabilityProvider;
use BackedEnum;
use DateTimeZone;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** HU-20 · to-be-10: la psicologa bloquea su agenda (vacaciones, congreso). */
class ScheduleBlockResource extends Resource
{
    protected static ?string $model = ScheduleBlock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?string $modelLabel = 'bloqueo de agenda';

    protected static ?string $pluralModelLabel = 'bloqueos de agenda';

    protected static ?string $navigationLabel = 'Bloqueos de agenda';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DateTimePicker::make('starts_at')->label('Desde')->seconds(false)->required(),
            DateTimePicker::make('ends_at')->label('Hasta')->seconds(false)->after('starts_at')->required(),
            TextInput::make('reason')->label('Motivo')->maxLength(255)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('starts_at')->label('Desde')->dateTime('D j M Y · H:i'),
                TextColumn::make('ends_at')->label('Hasta')->dateTime('D j M Y · H:i'),
                TextColumn::make('reason')->label('Motivo'),
            ])
            ->recordActions([
                DeleteAction::make()->label('Quitar bloqueo')->after(fn (ScheduleBlock $record) => self::forgetAvailability($record)),
            ]);
    }

    /** Los dias bloqueados dejan de ofrecerse de inmediato, sin esperar a que caduque la cache. */
    public static function forgetAvailability(ScheduleBlock $block): void
    {
        $timezone = new DateTimeZone(config('clinic.timezone'));

        for ($day = $block->starts_at->copy(); $day->lte($block->ends_at); $day = $day->addDay()) {
            DatabaseAvailabilityProvider::forget($block->therapist_id, $day->toDateTimeImmutable(), $timezone);
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScheduleBlocks::route('/'),
            'create' => CreateScheduleBlock::route('/create'),
        ];
    }
}
