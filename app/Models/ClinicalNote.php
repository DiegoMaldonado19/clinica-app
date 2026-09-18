<?php

namespace App\Models;

use App\Casts\ClinicalEncrypted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Solo lectura del historial para la psicologa. Escribe unicamente
 * ClinicalNoteService; una nota sellada ademas la protege el trigger.
 *
 * @property int $version
 * @property Carbon|null $sealed_at
 */
class ClinicalNote extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'soap_subjective' => ClinicalEncrypted::class,
            'soap_objective' => ClinicalEncrypted::class,
            'soap_assessment' => ClinicalEncrypted::class,
            'soap_plan' => ClinicalEncrypted::class,
            'sealed_at' => 'datetime',
        ];
    }
}
