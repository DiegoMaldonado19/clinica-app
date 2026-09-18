<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            CatalogSeeder::class,
            BusinessRuleSettingsSeeder::class,
            RoleAbilitySeeder::class,
            ClinicSeeder::class,
            NotificationTemplateSeeder::class,
        ]);

        // Contrasenas conocidas solo en la maquina del desarrollador.
        if (app()->isLocal()) {
            $this->call(DemoSeeder::class);
        }
    }
}
