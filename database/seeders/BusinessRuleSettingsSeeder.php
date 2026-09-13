<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Valores iniciales de RN-01 a RN-20 (Entregable 1, doc 04 §3). Las reglas sin
 * parametro configurable no tienen fila: no hay nada que ajustar en ellas.
 */
class BusinessRuleSettingsSeeder extends Seeder
{
    /**
     * Parte de la clave primaria, asi que tiene que ser constante: con `now()`
     * cada ejecucion insertaria una fila nueva en vez de actualizar la suya.
     */
    private const EFFECTIVE_FROM = '2026-01-01 00:00:00';

    public function run(): void
    {
        $now = now();

        $rows = array_map(
            fn (array $row): array => [
                'rule_code' => $row[0],
                'param_key' => $row[1],
                'param_value' => $row[2],
                'value_type' => $row[3],
                'effective_from' => self::EFFECTIVE_FROM,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                ['RN-01', 'same_day_booking_enabled',    'false',   'bool'],
                ['RN-02', 'min_booking_notice_hours',    '24',      'int'],
                ['RN-03', 'approval_sla_hours',          '24',      'int'],
                ['RN-03', 'approval_sla_weekend_hours',  '72',      'int'],
                ['RN-05', 'payment_deadline_hours_before', '3',     'int'],
                ['RN-06', 'cancellation_tiers',          '[{"h":24,"pct":0},{"h":1,"pct":50},{"h":0,"pct":100}]', 'json'],
                ['RN-08', 'reminder_offsets',            '[24,2]',  'json'],
                ['RN-09', 'auto_approve_payments',       'false',   'bool'],
                ['RN-15', 'temp_password_ttl_hours',     '72',      'int'],
                ['RN-15', 'otp_ttl_minutes',             '10',      'int'],
                // `refund_mode` es una cadena y el tipo `string` no existe en el
                // enum del doc 06 §3.10; se guarda como JSON en vez de ampliarlo.
                ['RN-16', 'refund_mode',                 '"credit"', 'json'],
                ['RN-17', 'quiet_hours',                 '21:00-07:00', 'duration'],
                ['RN-20', 'crisis_escalation_enabled',   'true',    'bool'],
            ]
        );

        DB::table('business_rule_settings')->upsert(
            $rows,
            ['rule_code', 'param_key', 'effective_from'],
            ['param_value', 'value_type', 'updated_at']
        );
    }
}
