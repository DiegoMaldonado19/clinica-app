<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            CatalogSeeder::class,
            BusinessRuleSettingsSeeder::class,
            RoleAbilitySeeder::class,
            NotificationTemplateSeeder::class,
        ]);

        // Cada despliegue corre las semillas. Esta reescribe servicios y horario,
        // asi que solo siembra una base que todavia no tiene la clinica.
        if (! DB::table('therapists')->exists()) {
            $this->call(ClinicSeeder::class);
        }

        // Contrasenas conocidas solo en la maquina del desarrollador.
        if (app()->isLocal()) {
            $this->call(DemoSeeder::class);
        }
    }
}
