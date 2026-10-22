<?php

declare(strict_types=1);

use App\Notifications\Infrastructure\SendNotificationJob;
use App\Support\PolicyText;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    config(['services.chatbot.token' => 'token-de-prueba']);
    $this->travelTo(gtAt('2026-09-15 10:00'));
});

/** @return array<string, string> */
function booking(array $overrides = []): array
{
    return $overrides + [
        'service_id' => serviceId(),
        'starts_at' => gtAt('2026-09-18 15:00')->toIso8601String(),
        'name' => 'Ana Pérez',
        'email' => 'ana.chat@correo.test',
        'phone' => '55551234',
        'nit' => 'CF',
        'accepted' => true,
        'ip_address' => '10.0.0.9',
    ];
}

it('rechaza la API sin el token de servicio, o con uno equivocado', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
    $this->withToken('otro-token')->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/v1/knowledge'],
    ['GET', '/api/v1/availability'],
    ['POST', '/api/v1/appointments'],
    ['POST', '/api/v1/crisis-alerts'],
]);

it('falla cerrada si el token no esta configurado', function () {
    config(['services.chatbot.token' => '']);

    $this->withToken('')->getJson('/api/v1/knowledge')->assertUnauthorized();
});

it('limita las peticiones del asistente', function () {
    foreach (range(1, 60) as $request) {
        $this->withToken('token-de-prueba')->getJson('/api/v1/knowledge')->assertOk();
    }

    $this->withToken('token-de-prueba')->getJson('/api/v1/knowledge')->assertTooManyRequests();
})->skip(fn (): bool => getenv('CACHE_STORE') === 'null', 'Sin cache el limitador no cuenta (ADR-008).');

it('entrega servicios, politicas, horario, la FAQ y la linea de crisis (HU-24)', function () {
    $this->withToken('token-de-prueba')->getJson('/api/v1/knowledge')
        ->assertOk()
        ->assertJsonPath('clinic.crisis_line', config('clinic.crisis_line'))
        ->assertJsonFragment(['name' => 'Terapia de pareja', 'price' => 'Q 500'])
        ->assertJsonFragment(['day' => 'lunes', 'from' => '08:00', 'to' => '12:00'])
        ->assertJsonPath('faqs.0.question', '¿Cómo es la primera cita?')
        ->assertJsonPath('policies.cancellation.0.fee', 'Sin cargo');
});

it('ofrece los horarios de la landing y ninguno para hoy (RN-01)', function () {
    $this->withToken('token-de-prueba')->getJson('/api/v1/availability?service_id='.serviceId().'&date=2026-09-15')
        ->assertOk()
        ->assertJsonCount(0, 'slots')
        ->assertJsonPath('notice', 'Las citas para el mismo día se coordinan por teléfono al '.config('clinic.phone').'.');

    $this->withToken('token-de-prueba')->getJson('/api/v1/availability?service_id='.serviceId().'&date=2026-09-18')
        ->assertOk()
        ->assertJsonPath('slots.0', ['starts_at' => gtAt('2026-09-18 08:00')->format(DateTimeInterface::ATOM), 'time' => '08:00']);
});

it('agenda con origen chat y el consentimiento vigente (HU-25)', function () {
    $this->withToken('token-de-prueba')->postJson('/api/v1/appointments', booking())
        ->assertCreated()
        ->assertJsonPath('message', 'Recibimos tu solicitud de Terapia individual para el viernes 18 de septiembre a las 15:00. Te confirmaremos por correo en menos de 24 horas.');

    $appointment = DB::table('appointments')->sole();

    expect($appointment->source)->toBe('chat')
        ->and(statusOf($appointment->id))->toBe('SOLICITADA')
        ->and(DB::table('consents')->pluck('document_version', 'document_code')->all())->toBe(PolicyText::consentVersions())
        ->and(PolicyText::consentVersions()['PRIVACIDAD'])->toBe('2026-10')
        ->and(dispatchedTemplates())->toContain('NT-01', 'NT-03');
});

it('no agenda sin la politica aceptada, el mismo dia ni fuera del calendario (HU-25)', function () {
    $this->withToken('token-de-prueba')->postJson('/api/v1/appointments', booking(['accepted' => false]))
        ->assertUnprocessable()->assertJsonValidationErrors('accepted');

    $this->withToken('token-de-prueba')->postJson('/api/v1/appointments', booking(['starts_at' => gtAt('2026-09-15 16:00')->toIso8601String()]))
        ->assertUnprocessable()->assertJsonPath('message', 'Las citas para el mismo día se coordinan por teléfono.');

    $this->withToken('token-de-prueba')->postJson('/api/v1/appointments', booking(['starts_at' => gtAt('2026-09-18 02:00')->toIso8601String()]))
        ->assertConflict();

    expect(DB::table('appointments')->count())->toBe(0);
});

it('avisa con NT-24 a la psicologa aun en horas de silencio, una vez por sesion (RN-20)', function () {
    Queue::fake();
    $this->travelTo(gtAt('2026-09-15 23:30'));

    foreach (range(1, 2) as $attempt) {
        $this->withToken('token-de-prueba')->postJson('/api/v1/crisis-alerts', ['session_id' => 'sesion-1', 'signal' => 'autolesion'])
            ->assertAccepted()->assertJsonPath('notified', 1);
    }

    $dispatch = DB::table('notification_dispatches')->sole();

    expect($dispatch->template_code)->toBe('NT-24')
        ->and($dispatch->scheduled_for)->toBe(gtAt('2026-09-15 23:30')->format('Y-m-d H:i:s'));
    Queue::assertPushed(SendNotificationJob::class, 1);
});

it('no admite una senal fuera del catalogo ni avisa con RN-20 desactivada', function () {
    $this->withToken('token-de-prueba')->postJson('/api/v1/crisis-alerts', ['session_id' => 's', 'signal' => 'texto libre de la persona'])
        ->assertUnprocessable();

    DB::table('business_rule_settings')->insert([
        'rule_code' => 'RN-20', 'param_key' => 'crisis_escalation_enabled', 'value_type' => 'bool',
        'param_value' => 'false', 'effective_from' => now()->subMinute(),
    ]);

    $this->withToken('token-de-prueba')->postJson('/api/v1/crisis-alerts', ['session_id' => 's', 'signal' => 'peligro'])
        ->assertAccepted()->assertJsonPath('notified', 0);
});
