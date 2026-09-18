<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * La clinica tal como la describe el sistema de diseno: una psicologa, cuatro
 * servicios y el horario confirmado en DP-03/DP-04. Idempotente: cada fila se
 * busca por su clave natural antes de insertarse.
 */
class ClinicSeeder extends Seeder
{
    public const THERAPIST_EMAIL = 'psicologa@clinica.test';

    private const PRICES_FROM = '2026-01-01 00:00:00';

    /** @var list<array{category: string, name: string, minutes: int, cents: int}> */
    private const SERVICES = [
        ['category' => 'INDIVIDUAL', 'name' => 'Terapia individual', 'minutes' => 50, 'cents' => 30000],
        ['category' => 'PAREJA',     'name' => 'Terapia de pareja',  'minutes' => 60, 'cents' => 50000],
        ['category' => 'FAMILIAR',   'name' => 'Terapia familiar',   'minutes' => 60, 'cents' => 55000],
        ['category' => 'INFANTIL',   'name' => 'Terapia infantil',   'minutes' => 45, 'cents' => 35000],
    ];

    /** L-V 08:00-12:00 y 15:00-19:00, sabado 08:00-12:00, en hora local. */
    private const HOURS = [
        [1, '08:00', '12:00'], [1, '15:00', '19:00'],
        [2, '08:00', '12:00'], [2, '15:00', '19:00'],
        [3, '08:00', '12:00'], [3, '15:00', '19:00'],
        [4, '08:00', '12:00'], [4, '15:00', '19:00'],
        [5, '08:00', '12:00'], [5, '15:00', '19:00'],
        [6, '08:00', '12:00'],
    ];

    public function run(): void
    {
        $now = now();

        foreach (self::SERVICES as $service) {
            $id = $this->idOrInsert('services', ['name' => $service['name']], [
                'category_id' => DB::table('service_categories')->where('code', $service['category'])->value('id'),
                'duration_minutes' => $service['minutes'],
                'is_active' => true,
            ]);

            DB::table('service_prices')->upsert(
                [[
                    'service_id' => $id, 'amount_cents' => $service['cents'], 'currency' => 'GTQ',
                    'effective_from' => self::PRICES_FROM, 'created_at' => $now, 'updated_at' => $now,
                ]],
                ['service_id', 'effective_from'],
                ['amount_cents', 'updated_at'],
            );
        }

        // Nace sin contrasena utilizable: se entra por "olvide mi contrasena" o
        // con la cuenta demo en local.
        $therapistId = $this->idOrInsert('users', ['email' => self::THERAPIST_EMAIL], [
            'role_id' => DB::table('roles')->where('code', 'admin')->value('id'),
            'name' => 'Psic. Andrea Morales',
            'password' => Hash::make(Str::random(40)),
            'is_active' => true,
        ]);

        DB::table('therapists')->insertOrIgnore(['id' => $therapistId, 'created_at' => $now, 'updated_at' => $now]);

        DB::table('availability_rules')->where('therapist_id', $therapistId)->delete();
        DB::table('availability_rules')->insert(array_map(
            fn (array $hours): array => [
                'therapist_id' => $therapistId, 'weekday' => $hours[0], 'starts_at' => $hours[1], 'ends_at' => $hours[2],
                'created_at' => $now, 'updated_at' => $now,
            ],
            self::HOURS,
        ));
    }

    /**
     * @param  array<string, mixed>  $key
     * @param  array<string, mixed>  $values
     */
    private function idOrInsert(string $table, array $key, array $values): string
    {
        $id = DB::table($table)->where($key)->value('id');

        if ($id !== null) {
            return (string) $id;
        }

        $id = (string) Str::uuid7();
        DB::table($table)->insert(['id' => $id] + $key + $values + ['created_at' => now(), 'updated_at' => now()]);

        return $id;
    }
}
