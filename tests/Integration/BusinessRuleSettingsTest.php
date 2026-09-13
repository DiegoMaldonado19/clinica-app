<?php

declare(strict_types=1);

use App\Support\BusinessRuleSettings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->settings = new BusinessRuleSettings;
});

it('devuelve cada parametro con el tipo que declara', function (string $rule, string $key, mixed $expected) {
    expect($this->settings->value($rule, $key))->toBe($expected);
})->with([
    ['RN-02', 'min_booking_notice_hours', 24],
    ['RN-01', 'same_day_booking_enabled', false],
    ['RN-20', 'crisis_escalation_enabled', true],
    ['RN-17', 'quiet_hours', '21:00-07:00'],
    ['RN-16', 'refund_mode', 'credit'],
    ['RN-08', 'reminder_offsets', [24, 2]],
]);

it('toma el valor vigente y no el programado a futuro', function () {
    DB::table('business_rule_settings')->insert([
        'rule_code' => 'RN-02',
        'param_key' => 'min_booking_notice_hours',
        'param_value' => '48',
        'value_type' => 'int',
        'effective_from' => now()->addMonth(),
    ]);

    expect($this->settings->value('RN-02', 'min_booking_notice_hours'))->toBe(24);
});

it('falla si el parametro no existe en vez de inventar un valor por defecto', function () {
    $this->settings->value('RN-99', 'inventado');
})->throws(RuntimeException::class);
