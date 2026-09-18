<?php

namespace App\Filament\Resources\ScheduleBlocks\Pages;

use App\Filament\Resources\ScheduleBlocks\ScheduleBlockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListScheduleBlocks extends ListRecords
{
    protected static string $resource = ScheduleBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Bloquear agenda')];
    }
}
