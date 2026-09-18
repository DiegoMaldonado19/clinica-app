<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use App\Models\Service;
use Filament\Resources\Pages\CreateRecord;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;

    private int $initialPriceCents = 0;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->initialPriceCents = (int) round((float) $data['initial_price'] * 100);
        unset($data['initial_price']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Service $service */
        $service = $this->record;
        $service->prices()->create(['amount_cents' => $this->initialPriceCents, 'currency' => 'GTQ', 'effective_from' => now()]);
    }
}
