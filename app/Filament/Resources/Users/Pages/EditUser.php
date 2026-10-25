<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Identity\Infrastructure\TemporaryPassword;
use App\Shared\Infrastructure\Audit\AuditLog;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    // Sin `DeleteAction`: la baja de un usuario es logica (doc 06 §7).
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * RN-15: la contraseña que asigna otra persona vuelve a ser temporal.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return filled($data['password'] ?? null) ? app(TemporaryPassword::class)->attributes($data['password']) + $data : $data;
    }

    protected function afterSave(): void
    {
        if ($this->record->wasChanged('role_id')) {
            AuditLog::record('user.role_changed', 'user', (string) $this->record->getKey(), [
                'before' => $this->record->getPrevious()['role_id'] ?? null,
                'after' => $this->record->getAttribute('role_id'),
            ]);
        }

        if ($this->record->wasChanged('password')) {
            AuditLog::record('user.password_reset', 'user', (string) $this->record->getKey());
        }
    }
}
