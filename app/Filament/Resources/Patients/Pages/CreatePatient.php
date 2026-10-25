<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Patients\PatientResource;
use App\Identity\Infrastructure\DatabasePatientRegistry;
use App\Models\Patient;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/** La cuenta y la ficha en un solo paso, sin pasar por Usuarios (F3-10). */
class CreatePatient extends CreateRecord
{
    protected static string $resource = PatientResource::class;

    // Si la ficha falla, no queda una cuenta suelta ni un NT-02 ya encolado.
    protected ?bool $hasDatabaseTransactions = true;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        $id = app(DatabasePatientRegistry::class)->registerAccount(
            $data['name'], $data['email'], $data['phone_e164'] ?? null, $data['avatar_path'] ?? null,
        );

        return Patient::create(['id' => $id] + Arr::except($data, ['name', 'email', 'phone_e164', 'avatar_path']));
    }
}
