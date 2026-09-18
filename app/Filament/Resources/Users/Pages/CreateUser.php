<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Shared\Infrastructure\Audit\AuditLog;
use App\Support\BusinessRuleSettings;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * RN-15: toda alta nace con credencial temporal. El TTL sale de
     * `business_rule_settings`, no de una constante.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $hours = app(BusinessRuleSettings::class)->value('RN-15', 'temp_password_ttl_hours');

        $data['must_change_password'] = true;
        $data['temp_password_expires_at'] = now()->addHours((int) $hours);

        return $data;
    }

    protected function afterCreate(): void
    {
        AuditLog::record('user.created', 'user', (string) $this->record->getKey(), ['role_id' => $this->record->getAttribute('role_id')]);
    }
}
