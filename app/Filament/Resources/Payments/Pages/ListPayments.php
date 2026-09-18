<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    public function getTabs(): array
    {
        return [
            'Por conciliar' => Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->whereRelation('status', 'code', 'EN_REVISION')),
            'Todos' => Tab::make(),
        ];
    }
}
