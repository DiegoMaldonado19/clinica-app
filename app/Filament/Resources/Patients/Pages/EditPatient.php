<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Patients\PatientResource;
use Filament\Resources\Pages\EditRecord;

class EditPatient extends EditRecord
{
    protected static string $resource = PatientResource::class;

    // Sin `DeleteAction`: el expediente no se borra (doc 06 §7).
    protected function getHeaderActions(): array
    {
        return [];
    }
}
