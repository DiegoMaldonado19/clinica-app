<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fila del catalogo `appointment_statuses`. El comportamiento de cada estado
 * vive en el enum del dominio, no aqui.
 *
 * @property string $code
 * @property string $label
 */
class AppointmentStatus extends Model {}
