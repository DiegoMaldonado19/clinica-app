<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * La matriz de permisos del doc 05 §4.2, literal. Un permiso mal asignado aqui
 * se descubre en el expediente clinico, que es donde mas caro sale.
 */
class RoleAbilitySeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const MATRIX = [
        'admin' => [
            'appointment.view.any', 'appointment.view.own', 'appointment.create',
            'appointment.approve', 'appointment.reject', 'appointment.cancel.any',
            'appointment.cancel.own', 'appointment.checkin', 'appointment.mark_no_show',
            'schedule.block', 'availability.read',
            'payment.view.any', 'payment.view.own', 'payment.submit', 'payment.approve',
            'payment.waive_fee',
            'patient.view.any', 'patient.create', 'patient.update',
            'clinical_intake.view', 'clinical_intake.create',
            'clinical_note.view', 'clinical_note.create', 'clinical_note.seal',
            'clinical_note.amend',
            'psych_test.assign',
            'user.create.staff',
            'report.view.operational', 'report.view.financial',
            'settings.manage', 'audit.view',
        ],
        // Recepcion no toca contenido clinico ni reportes financieros: es la
        // separacion del doc 05 §4.3 y no se relaja.
        'secretary' => [
            'appointment.view.any', 'appointment.view.own', 'appointment.create',
            'appointment.approve', 'appointment.reject', 'appointment.cancel.any',
            'appointment.cancel.own', 'appointment.checkin', 'appointment.mark_no_show',
            'availability.read',
            'payment.view.any', 'payment.view.own', 'payment.submit', 'payment.approve',
            'patient.view.any', 'patient.create', 'patient.update',
            'clinical_intake.view', 'clinical_intake.create',
            'report.view.operational',
        ],
        'patient' => [
            'appointment.view.own', 'appointment.create', 'appointment.cancel.own',
            'availability.read',
            'payment.view.own', 'payment.submit',
            'psych_test.answer',
        ],
    ];

    public function run(): void
    {
        $roles = DB::table('roles')->pluck('id', 'code');
        $abilities = DB::table('abilities')->pluck('id', 'code');

        $rows = [];

        foreach (self::MATRIX as $role => $codes) {
            foreach ($codes as $code) {
                $rows[] = ['role_id' => $roles[$role], 'ability_id' => $abilities[$code]];
            }
        }

        // Se reescribe entera en vez de insertar lo que falte: asi revocar un
        // permiso de la matriz tambien lo revoca en la base.
        DB::transaction(function () use ($rows) {
            DB::table('role_ability')->delete();
            DB::table('role_ability')->insert($rows);
        });
    }
}
