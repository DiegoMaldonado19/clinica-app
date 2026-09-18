<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Dos veces a proposito: una semilla no idempotente duplica catalogos en el
    // segundo despliegue, y eso solo se ve ejecutandola dos veces.
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);
});

it('siembra cada catalogo una sola vez', function (string $table, int $expected) {
    expect(DB::table($table)->count())->toBe($expected);
})->with([
    ['roles', 3],
    ['abilities', 32],
    ['appointment_statuses', 13],
    ['payment_statuses', 6],
    ['payment_methods', 2],
    ['notification_channels', 4],
    ['notification_statuses', 5],
    ['cancellation_reasons', 5],
    ['service_categories', 4],
    ['sexes', 4],
    ['document_types', 3],
]);

it('deja WHATSAPP y SMS inactivos', function () {
    expect(DB::table('notification_channels')->where('is_active', false)->pluck('code')->all())
        ->toEqualCanonicalizing(['WHATSAPP', 'SMS']);
});

it('siembra los tres tramos de cancelacion de RN-06', function () {
    $value = DB::table('business_rule_settings')
        ->where('rule_code', 'RN-06')
        ->where('param_key', 'cancellation_tiers')
        ->value('param_value');

    expect(json_decode((string) $value, true))->toBe([
        ['h' => 24, 'pct' => 0],
        ['h' => 1,  'pct' => 50],
        ['h' => 0,  'pct' => 100],
    ]);
});
