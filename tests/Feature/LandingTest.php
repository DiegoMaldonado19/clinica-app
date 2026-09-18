<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('muestra servicios, tarifas y la politica vigente (M2, M5)', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSeeInOrder(['Terapia infantil', 'Terapia individual'])
        ->assertSee('Q 300')
        ->assertSee('24 horas o más de anticipación')
        ->assertSee('Menos de 1 hora, o no asistir');
});

it('refleja en la landing un cambio de la politica sin desplegar', function () {
    DB::table('business_rule_settings')->insert([
        'rule_code' => 'RN-06', 'param_key' => 'cancellation_tiers', 'value_type' => 'json',
        'param_value' => '[{"h":48,"pct":0},{"h":2,"pct":50},{"h":0,"pct":100}]', 'effective_from' => now()->subMinute(),
    ]);

    $this->get('/')->assertSee('48 horas o más de anticipación')->assertSee('Entre 48 horas y 2 horas');
});

it('abre el wizard de agendamiento', function () {
    $this->get('/agendar')->assertSuccessful()->assertSee('¿Qué tipo de terapia necesitas?');
});
