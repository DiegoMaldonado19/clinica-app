<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('crea la cuenta de la psicologa con el correo de ADMIN_EMAIL', function () {
    config(['clinic.admin_email' => 'andrea.morales@correo.test']);

    $this->seed(DatabaseSeeder::class);

    $email = DB::table('therapists')->join('users', 'users.id', '=', 'therapists.id')->value('users.email');

    expect($email)->toBe('andrea.morales@correo.test');
});

// Cada despliegue corre las semillas: lo que la psicologa cambio no se pisa.
it('no vuelve a sembrar servicios ni horario en una base que ya tiene la clinica', function () {
    $this->seed(DatabaseSeeder::class);
    DB::table('services')->where('name', 'Terapia individual')->update(['name' => 'Terapia individual (adultos)']);
    DB::table('availability_rules')->where('weekday', 6)->delete();

    $this->seed(DatabaseSeeder::class);

    expect(DB::table('services')->count())->toBe(4)
        ->and(DB::table('availability_rules')->where('weekday', 6)->exists())->toBeFalse();
});
