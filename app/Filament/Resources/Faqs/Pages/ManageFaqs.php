<?php

namespace App\Filament\Resources\Faqs\Pages;

use App\Filament\Resources\Faqs\FaqResource;
use App\Models\Faq;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFaqs extends ManageRecords
{
    protected static string $resource = FaqResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Las nuevas van al final de la lista; se reordenan arrastrando.
            CreateAction::make()->label('Nueva pregunta')
                ->mutateDataUsing(fn (array $data): array => $data + ['position' => (int) Faq::query()->max('position') + 1]),
        ];
    }
}
