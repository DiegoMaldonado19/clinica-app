<?php

declare(strict_types=1);

use App\Filament\Portal\Pages\EditProfile;
use App\Models\User;
use App\Notifications\Infrastructure\NotificationDispatcher;
use App\Notifications\Infrastructure\NotificationPreferences;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->patient = User::factory()->role('patient')->create(['phone_e164' => '+50255551234']);
    $this->travelTo(gtAt('2026-09-15 10:00'));
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
});

function configureWhatsApp(): void
{
    config(['services.whatsapp.token' => 'token-de-meta', 'services.whatsapp.phone_number_id' => '1234567890']);
}

function sendConfirmation(string $recipientId): void
{
    app(NotificationDispatcher::class)->dispatch('NT-08', 'evento-1', $recipientId, ['nombre' => 'Ana', 'servicio' => 'terapia', 'fecha' => 'viernes', 'hora' => '15:00']);
}

/** @return list<string> */
function dispatchedChannels(): array
{
    return DB::table('notification_dispatches')
        ->join('notification_channels', 'notification_channels.id', '=', 'notification_dispatches.channel_id')
        ->pluck('notification_channels.code')
        ->all();
}

it('envia por WhatsApp la plantilla generica con el aviso como variable', function () {
    configureWhatsApp();
    app(NotificationPreferences::class)->choose($this->patient->id, ['WHATSAPP']);

    sendConfirmation($this->patient->id);

    expect(dispatchedChannels())->toBe(['WHATSAPP'])
        ->and(DB::table('notification_dispatches')->value('status_id'))->toBe(DB::table('notification_statuses')->where('code', 'ENVIADO')->value('id'));
    Http::assertSent(function (Request $request): bool {
        $variables = array_column($request['template']['components'][0]['parameters'], 'text');

        return $request->url() === 'https://graph.facebook.com/v26.0/1234567890/messages'
            && $request->hasHeader('Authorization', 'Bearer token-de-meta')
            && $request['to'] === '50255551234'
            && $request['template']['name'] === 'aviso_clinica'
            && $variables[0] === 'Tu cita está confirmada'
            && str_starts_with($variables[1], 'Hola Ana: Tu pago fue aprobado')
            && ! str_contains($variables[1], "\n");
    });
});

it('cae al correo si WhatsApp no esta configurado aunque el paciente lo haya elegido', function () {
    app(NotificationPreferences::class)->choose($this->patient->id, ['WHATSAPP']);

    sendConfirmation($this->patient->id);

    expect(dispatchedChannels())->toBe(['MAIL']);
    Http::assertNothingSent();
});

it('respeta la eleccion de ambos canales, con un envio por canal (RN-17)', function () {
    configureWhatsApp();
    app(NotificationPreferences::class)->choose($this->patient->id, ['MAIL', 'WHATSAPP']);

    sendConfirmation($this->patient->id);
    sendConfirmation($this->patient->id);

    expect(dispatchedChannels())->toEqualCanonicalizing(['MAIL', 'WHATSAPP']);
});

it('guarda desde el perfil del portal los canales que elige el paciente', function () {
    configureWhatsApp();
    Filament::setCurrentPanel(Filament::getPanel('portal'));

    Livewire::actingAs($this->patient)->test(EditProfile::class)
        ->assertFormSet(['channels' => ['MAIL']])
        ->fillForm(['channels' => ['MAIL', 'WHATSAPP']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(NotificationPreferences::class)->of($this->patient->id))->toEqualCanonicalizing(['MAIL', 'WHATSAPP']);
});
