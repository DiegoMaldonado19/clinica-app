<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    // Sin `DeleteAction`: la baja de un usuario es logica (doc 06 §7).
    protected function getHeaderActions(): array
    {
        return [];
    }
}
