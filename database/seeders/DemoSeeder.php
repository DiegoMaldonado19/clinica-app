<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Cuentas de demostracion con la contrasena `password`. DatabaseSeeder solo la
 * llama con APP_ENV=local: en cualquier otro entorno serian una puerta abierta.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['email' => ClinicSeeder::THERAPIST_EMAIL, 'role' => 'admin',     'name' => 'Psic. Andrea Morales'],
            ['email' => 'recepcion@clinica.test',      'role' => 'secretary', 'name' => 'Lucía Recepción'],
            ['email' => 'paciente@clinica.test',       'role' => 'patient',   'name' => 'Ana Pérez'],
        ];

        foreach ($accounts as $account) {
            $user = User::updateOrCreate(['email' => $account['email']], [
                'role_id' => DB::table('roles')->where('code', $account['role'])->value('id'),
                'name' => $account['name'],
                'password' => 'password',
                'must_change_password' => false,
                'is_active' => true,
            ]);

            if ($account['role'] === 'patient') {
                Patient::firstOrCreate(['id' => $user->getKey()], ['nit' => 'CF']);
            }
        }
    }
}
