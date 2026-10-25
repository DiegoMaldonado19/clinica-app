<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Semilla determinista de los doce catalogos. Idempotente: `upsert` sobre
 * `code`, que es la clave natural.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seed('roles', [
            ['code' => 'admin',     'label' => 'Administradora'],
            ['code' => 'secretary', 'label' => 'Recepción'],
            ['code' => 'patient',   'label' => 'Paciente'],
        ]);

        // Los 32 permisos atomicos del doc 05 §4.2.
        $this->seed('abilities', [
            ['code' => 'appointment.view.any',       'label' => 'Ver cualquier cita'],
            ['code' => 'appointment.view.own',       'label' => 'Ver citas propias'],
            ['code' => 'appointment.create',         'label' => 'Crear cita'],
            ['code' => 'appointment.approve',        'label' => 'Aprobar cita'],
            ['code' => 'appointment.reject',         'label' => 'Rechazar cita'],
            ['code' => 'appointment.cancel.any',     'label' => 'Cancelar cualquier cita'],
            ['code' => 'appointment.cancel.own',     'label' => 'Cancelar cita propia'],
            ['code' => 'appointment.checkin',        'label' => 'Registrar llegada'],
            ['code' => 'appointment.mark_no_show',   'label' => 'Marcar inasistencia'],
            ['code' => 'schedule.block',             'label' => 'Bloquear agenda'],
            ['code' => 'availability.read',          'label' => 'Consultar disponibilidad'],
            ['code' => 'payment.view.any',           'label' => 'Ver cualquier pago'],
            ['code' => 'payment.view.own',           'label' => 'Ver pagos propios'],
            ['code' => 'payment.submit',             'label' => 'Registrar pago'],
            ['code' => 'payment.approve',            'label' => 'Aprobar pago'],
            ['code' => 'payment.waive_fee',          'label' => 'Exonerar recargo'],
            ['code' => 'patient.view.any',           'label' => 'Ver cualquier paciente'],
            ['code' => 'patient.create',             'label' => 'Crear paciente'],
            ['code' => 'patient.update',             'label' => 'Actualizar paciente'],
            ['code' => 'clinical_intake.view',       'label' => 'Ver ficha administrativa'],
            ['code' => 'clinical_intake.create',     'label' => 'Crear ficha administrativa'],
            ['code' => 'clinical_note.view',         'label' => 'Ver nota clínica'],
            ['code' => 'clinical_note.create',       'label' => 'Crear nota clínica'],
            ['code' => 'clinical_note.seal',         'label' => 'Sellar nota clínica'],
            ['code' => 'clinical_note.amend',        'label' => 'Enmendar nota clínica'],
            ['code' => 'psych_test.assign',          'label' => 'Asignar prueba psicométrica'],
            ['code' => 'psych_test.answer',          'label' => 'Responder prueba psicométrica'],
            ['code' => 'user.create.staff',          'label' => 'Crear usuario de personal'],
            ['code' => 'report.view.operational',    'label' => 'Ver reportes operativos'],
            ['code' => 'report.view.financial',      'label' => 'Ver reportes financieros'],
            ['code' => 'settings.manage',            'label' => 'Administrar parámetros'],
            ['code' => 'audit.view',                 'label' => 'Ver bitácora de auditoría'],
        ]);

        // Los 13 estados de la maquina (doc 08 §1). El orden es el del diagrama.
        $this->seed('appointment_statuses', [
            ['code' => 'SOLICITADA',                'label' => 'Solicitada'],
            ['code' => 'CONFIRMADA_PENDIENTE_PAGO', 'label' => 'Confirmada, pendiente de pago'],
            ['code' => 'PAGO_EN_REVISION',          'label' => 'Pago en revisión'],
            ['code' => 'PAGO_EN_CAJA',              'label' => 'Pago en caja'],
            ['code' => 'AGENDADA',                  'label' => 'Agendada'],
            ['code' => 'EN_CURSO',                  'label' => 'En curso'],
            ['code' => 'ATENDIDA',                  'label' => 'Atendida'],
            ['code' => 'RECHAZADA',                 'label' => 'Rechazada'],
            ['code' => 'EXPIRADA',                  'label' => 'Expirada'],
            ['code' => 'CANCELADA_IMPAGO',          'label' => 'Cancelada por impago'],
            ['code' => 'CANCELADA_SIN_CARGO',       'label' => 'Cancelada sin cargo'],
            ['code' => 'CANCELADA_CON_RECARGO',     'label' => 'Cancelada con recargo'],
            ['code' => 'NO_SHOW',                   'label' => 'No se presentó'],
        ]);

        $this->seed('payment_statuses', [
            ['code' => 'PENDIENTE',   'label' => 'Pendiente'],
            ['code' => 'EN_REVISION', 'label' => 'En revisión'],
            ['code' => 'APROBADO',    'label' => 'Aprobado'],
            ['code' => 'RECHAZADO',   'label' => 'Rechazado'],
            ['code' => 'EN_CAJA',     'label' => 'En caja'],
            ['code' => 'EXONERADO',   'label' => 'Exonerado'],
        ]);

        $this->seed('payment_methods', [
            ['code' => 'TRANSFERENCIA', 'label' => 'Transferencia bancaria'],
            ['code' => 'EFECTIVO',      'label' => 'Efectivo'],
        ]);

        $this->seed('banks', [
            ['code' => 'BI',         'label' => 'Banco Industrial'],
            ['code' => 'BANRURAL',   'label' => 'Banco de Desarrollo Rural'],
            ['code' => 'BAM',        'label' => 'Banco Agromercantil'],
            ['code' => 'GYT',        'label' => 'Banco G&T Continental'],
            ['code' => 'BAC',        'label' => 'Banco BAC Credomatic'],
            ['code' => 'PROMERICA',  'label' => 'Banco Promerica'],
            ['code' => 'INTERBANCO', 'label' => 'Interbanco'],
        ]);

        // is_active no activa un canal: lo decide la configuracion (ADR-035), porque esta
        // semilla reescribe la columna en cada despliegue.
        $this->seed('notification_channels', [
            ['code' => 'MAIL',     'label' => 'Correo electrónico'],
            ['code' => 'IN_APP',   'label' => 'En la aplicación'],
            ['code' => 'WHATSAPP', 'label' => 'WhatsApp', 'is_active' => false],
            ['code' => 'SMS',      'label' => 'Mensaje de texto', 'is_active' => false],
        ]);

        $this->seed('notification_statuses', [
            ['code' => 'PENDIENTE', 'label' => 'Pendiente'],
            ['code' => 'ENVIADO',   'label' => 'Enviado'],
            ['code' => 'ENTREGADO', 'label' => 'Entregado'],
            ['code' => 'FALLIDO',   'label' => 'Fallido'],
            ['code' => 'OMITIDO',   'label' => 'Omitido'],
        ]);

        $this->seed('cancellation_reasons', [
            ['code' => 'PACIENTE_SOLICITO',        'label' => 'El paciente lo solicitó'],
            ['code' => 'TERAPEUTA_NO_DISPONIBLE',  'label' => 'La terapeuta no está disponible'],
            ['code' => 'IMPAGO',                   'label' => 'Pago no acreditado'],
            ['code' => 'SLA_VENCIDO',              'label' => 'SLA de aprobación vencido'],
            ['code' => 'NO_SHOW',                  'label' => 'El paciente no se presentó'],
        ]);

        $this->seed('service_categories', [
            ['code' => 'INDIVIDUAL', 'label' => 'Terapia individual'],
            ['code' => 'PAREJA',     'label' => 'Terapia de pareja'],
            ['code' => 'FAMILIAR',   'label' => 'Terapia familiar'],
            ['code' => 'INFANTIL',   'label' => 'Terapia infantil'],
        ]);

        $this->seed('sexes', [
            ['code' => 'M',                 'label' => 'Masculino'],
            ['code' => 'F',                 'label' => 'Femenino'],
            ['code' => 'OTRO',              'label' => 'Otro'],
            ['code' => 'PREFIERO_NO_DECIR', 'label' => 'Prefiere no decirlo'],
        ]);

        $this->seed('document_types', [
            ['code' => 'DPI',       'label' => 'Documento personal de identificación'],
            ['code' => 'PASAPORTE', 'label' => 'Pasaporte'],
            ['code' => 'NIT',       'label' => 'Número de identificación tributaria'],
        ]);
    }

    /**
     * @param  list<array{code: string, label: string, is_active?: bool}>  $rows
     */
    private function seed(string $table, array $rows): void
    {
        $now = now();

        $rows = array_map(
            static fn (array $row): array => $row + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            $rows
        );

        DB::table($table)->upsert($rows, ['code'], ['label', 'is_active', 'updated_at']);
    }
}
