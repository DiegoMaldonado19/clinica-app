<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Plantillas del catalogo NT (doc 10 §7) que usa la Fase 2. Ninguna lleva
 * contenido clinico: NT-15 solo confirma que la sesion quedo registrada.
 * Los `:marcadores` los rellena el motor al enviar.
 */
class NotificationTemplateSeeder extends Seeder
{
    private const SIGNATURE = "\n\nClínica Psicología y Bienestar";

    /** @var array<string, array{0: string, 1: string}> */
    private const TEMPLATES = [
        'NT-01' => ['Recibimos tu solicitud de cita', "Hola :nombre:\n\nRecibimos tu solicitud de :servicio para el :fecha a las :hora. Te confirmaremos antes del :vence y luego te enviaremos el enlace para registrar tu pago. Ese horario queda apartado mientras tanto."],
        'NT-02' => ['Tu acceso al portal del paciente', "Hola :nombre:\n\nCreamos tu cuenta para que sigas tus citas y registres tus pagos.\n\nPortal: :portal\nCorreo: :correo\nContraseña temporal: :contrasena\n\nLa contraseña vence en :horas horas y te pediremos cambiarla la primera vez que ingreses."],
        'NT-03' => ['Nueva solicitud por aprobar', ":paciente solicitó :servicio para el :fecha a las :hora.\nSi no se resuelve antes del :vence, el horario se libera automáticamente.\n\nBandeja: :bandeja"],
        'NT-04' => ['Tu cita fue aprobada: registra tu pago', "Hola :nombre:\n\nAprobamos tu cita de :servicio para el :fecha a las :hora. Registra tu pago de :monto antes del :limite desde tu portal: :portal\n\nTambién puedes elegir pagar en efectivo al llegar."],
        'NT-05' => ['No pudimos confirmar tu solicitud', "Hola :nombre:\n\nNo pudimos confirmar tu solicitud del :fecha a las :hora.\nMotivo: :motivo\n\nPuedes elegir otro horario en :sitio"],
        'NT-06' => ['Tu solicitud venció', "Hola :nombre:\n\nNo alcanzamos a confirmar tu solicitud del :fecha a las :hora y el horario se liberó. Lamentamos el inconveniente; puedes solicitar otro horario en :sitio"],
        'NT-07' => ['Comprobante por conciliar', ":paciente registró un comprobante por :monto para su cita del :fecha a las :hora.\n\nConciliar: :pagos"],
        'NT-08' => ['Tu cita está confirmada', "Hola :nombre:\n\nTu pago fue aprobado y tu cita de :servicio del :fecha a las :hora quedó confirmada. Te recomendamos llegar 10 minutos antes."],
        'NT-09' => ['Revisamos tu comprobante', "Hola :nombre:\n\nNo pudimos aprobar el comprobante de tu cita del :fecha a las :hora.\nMotivo: :motivo\n\nPuedes registrar uno nuevo desde tu portal antes del :limite: :portal"],
        'NT-10' => ['Tu cita fue cancelada por falta de pago', "Hola :nombre:\n\nTu cita del :fecha a las :hora se canceló porque el pago no estaba aprobado a tiempo, y el horario se liberó. Puedes solicitar otro horario en :sitio"],
        'NT-11' => ['Recordatorio: tu cita es mañana', "Hola :nombre:\n\nTe recordamos tu cita de :servicio el :fecha a las :hora. Si necesitas cancelar, hazlo desde tu portal: :portal\n\nCancelar con 24 horas o más de anticipación no tiene costo."],
        'NT-12' => ['Recordatorio: tu cita es en 2 horas', "Hola :nombre:\n\nTu cita de :servicio es hoy a las :hora. Te esperamos."],
        'NT-13' => ['Tu cita fue cancelada', "Hola :nombre:\n\nCancelamos tu cita del :fecha a las :hora. :detalle"],
        'NT-14' => ['Cargo por cancelación tardía', "Hola :nombre:\n\nPor cancelar tu cita del :fecha a las :hora con menos de 24 horas de anticipación se generó un cargo de :monto (:porcentaje % de la tarifa)."],
        'NT-15' => ['Tu sesión quedó registrada', "Hola :nombre:\n\nGracias por asistir a tu sesión del :fecha. Cuando quieras agendar la siguiente, puedes hacerlo en :sitio"],
        'NT-20' => ['Falla de entrega de una notificación', "La notificación :plantilla dirigida a :destinatario no pudo entregarse después de tres intentos.\nÚltimo error: :error"],
        'NT-22' => ['Tu cita fue cancelada por indisponibilidad', "Hola :nombre:\n\nLa psicóloga no estará disponible el :fecha a las :hora, por lo que cancelamos tu cita sin ningún cargo. :detalle\n\nPuedes elegir otro horario en :sitio"],
        'NT-23' => ['Tienes un crédito a favor', "Hola :nombre:\n\nGeneramos un crédito a favor de :monto por tu cita del :fecha. Se aplicará a tu próxima cita."],
    ];

    public function run(): void
    {
        $now = now();
        $rows = [];

        foreach (self::TEMPLATES as $code => [$subject, $body]) {
            $rows[] = [
                'code' => $code, 'subject' => $subject, 'body' => $body.self::SIGNATURE,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ];
        }

        DB::table('notification_templates')->upsert($rows, ['code'], ['subject', 'body', 'updated_at']);
    }
}
