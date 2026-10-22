<?php

declare(strict_types=1);

use App\Livewire\ChatWidget;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    config(['services.chatbot.token' => 'token-de-prueba']);
    $this->travelTo(gtAt('2026-09-15 10:00'));
});

/** @return array<string, mixed> */
function chatReply(array $overrides = []): array
{
    return $overrides + ['reply' => 'Con gusto te ayudo.', 'sources' => [], 'escalated' => false, 'closed' => false, 'action' => null];
}

it('muestra siempre la linea de crisis y el aviso del proveedor de IA (RF-62)', function () {
    $this->get('/')
        ->assertSee('Línea de crisis 24/7')
        ->assertSee(config('clinic.crisis_line'))
        ->assertSee('Tus mensajes los procesa un proveedor de inteligencia artificial.');
});

it('envia la conversacion al asistente y muestra la respuesta con su fuente (HU-24)', function () {
    Http::fake(['chatbot:8000/chat' => Http::response(chatReply(['reply' => 'La terapia de pareja cuesta Q 500.', 'sources' => ['Servicios y tarifas']]))]);

    Livewire::test(ChatWidget::class)->set('draft', '¿Cuánto cuesta la terapia de pareja?')->call('send')
        ->assertSee('La terapia de pareja cuesta Q 500.')
        ->assertSee('Fuente: Servicios y tarifas');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://chatbot:8000/chat'
        && $request->hasHeader('Authorization', 'Bearer token-de-prueba')
        && $request['messages'] === [['role' => 'user', 'content' => '¿Cuánto cuesta la terapia de pareja?']]);
});

it('ante una crisis muestra la linea y no sigue la conversacion (RF-61)', function () {
    Http::fake(['chatbot:8000/chat' => Http::response(chatReply(['reply' => 'Llama a la línea de crisis.', 'escalated' => true, 'closed' => true]))]);

    Livewire::test(ChatWidget::class)->set('draft', 'Ya no quiero vivir')->call('send')
        ->assertSet('closed', true)
        ->assertSee('Llama ahora a la línea de crisis')
        ->assertDontSee('Escribe tu mensaje')
        ->set('draft', 'Hola')->call('send');

    Http::assertSentCount(1);
});

it('agenda solo con los datos completos y la politica aceptada (HU-25)', function () {
    Http::fake([
        'chatbot:8000/chat' => Http::response(chatReply([
            'reply' => 'Completa el formulario para confirmar.',
            'action' => ['service_id' => serviceId(), 'service' => 'Terapia individual', 'starts_at' => gtAt('2026-09-18 15:00')->toIso8601String(), 'date' => '2026-09-18', 'time' => '15:00'],
        ])),
        'chatbot:8000/bookings' => Http::response(['ok' => true, 'reply' => 'Recibimos tu solicitud.']),
    ]);

    Livewire::test(ChatWidget::class)->set('draft', 'El viernes a las 3')->call('send')
        ->assertSee('Viernes 18 de septiembre · 15:00')
        ->assertSee('24 horas o más de anticipación')
        ->set('name', 'Ana Pérez')->set('email', 'ana.chat@correo.test')->set('phone', '55551234')->set('nit', 'CF')
        ->call('book')->assertHasErrors('accepted')
        ->set('accepted', true)->call('book')
        ->assertHasNoErrors()
        ->assertSee('Recibimos tu solicitud.')
        ->assertSet('booking', null);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://chatbot:8000/bookings'
        && $request['accepted'] === true && $request['phone'] === '55551234');
});

it('avisa con el telefono si el asistente no responde', function () {
    Http::fake(['chatbot:8000/*' => Http::response(status: 503)]);

    Livewire::test(ChatWidget::class)->set('draft', 'Hola')->call('send')
        ->assertSee('El asistente no está disponible en este momento. Llámanos al '.config('clinic.phone'));
});

it('admite 30 mensajes por minuto por IP (doc 05 §6)', function () {
    Http::fake(['chatbot:8000/*' => Http::response(chatReply())]);
    $widget = Livewire::test(ChatWidget::class);

    foreach (range(1, 31) as $message) {
        $widget->set('draft', "Mensaje {$message}")->call('send');
    }

    $widget->assertSee('Escribiste muchos mensajes seguidos');
    Http::assertSentCount(30);
})->skip(fn (): bool => getenv('CACHE_STORE') === 'null', 'Sin cache el limitador no cuenta (ADR-008).');
