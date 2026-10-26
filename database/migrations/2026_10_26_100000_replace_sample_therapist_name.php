<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La cuenta de la psicologa nacio con el nombre de muestra del sistema de diseno,
 * y ClinicSeeder no toca una cuenta que ya existe: las bases sembradas antes de
 * `clinic.therapist` lo conservan y la bitacora lo muestra. Solo se reemplaza ese
 * nombre; el que ella haya puesto en su perfil se respeta. Sin `down()`: el nombre
 * de muestra no se restaura.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereIn('id', DB::table('therapists')->select('id'))
            ->where('name', 'Psic. Andrea Morales')
            ->update(['name' => config('clinic.therapist'), 'updated_at' => now()]);
    }
};
