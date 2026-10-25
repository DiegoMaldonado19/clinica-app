<?php

declare(strict_types=1);

namespace App\Filament\Portal\Pages;

use App\Filament\Schemas\PersonFields;
use App\Notifications\Infrastructure\NotificationDispatcher;
use App\Notifications\Infrastructure\NotificationPreferences;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/** El perfil de Filament más la elección de canal para los avisos de las citas (F3-07). */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            PersonFields::avatar(),
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            CheckboxList::make('channels')
                ->label('Quiero recibir los avisos de mis citas por')
                ->options(fn (): array => $this->channelOptions())
                ->required()
                ->helperText('Si un canal deja de estar disponible, te escribimos por correo.'),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data + ['channels' => app(NotificationPreferences::class)->of((string) $this->getUser()->getKey()) ?: ['MAIL']];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(NotificationPreferences::class)->choose((string) $record->getKey(), array_values(Arr::pull($data, 'channels', [])));

        return parent::handleRecordUpdate($record, $data);
    }

    /** @return array<string, string> los canales activos; WhatsApp solo si hay un teléfono al que escribir */
    private function channelOptions(): array
    {
        $active = app(NotificationDispatcher::class)->activeChannels();

        if (blank($this->getUser()->getAttribute('phone_e164'))) {
            $active = array_diff($active, ['WHATSAPP']);
        }

        return DB::table('notification_channels')->whereIn('code', $active)->orderBy('id')->pluck('label', 'code')->all();
    }
}
