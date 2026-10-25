<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Identity\Infrastructure\TemporaryPassword;
use App\Shared\Infrastructure\Audit\AuditLog;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * RN-15: toda alta nace con credencial temporal.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(TemporaryPassword::class)->attributes($data['password']) + $data;
    }

    protected function afterCreate(): void
    {
        AuditLog::record('user.created', 'user', (string) $this->record->getKey(), ['role_id' => $this->record->getAttribute('role_id')]);
    }
}
