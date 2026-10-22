<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ficha administrativa (clinical_intake): la ve y la edita recepcion. No tiene
 * contenido clinico; las notas SOAP viven en `clinical_notes`.
 *
 * @property string $patient_id
 */
#[Fillable(['patient_id', 'reported_reason', 'referred_by', 'opened_at'])]
class ClinicalRecord extends Model
{
    use HasUuids;

    /** @return BelongsTo<User, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['opened_at' => 'datetime'];
    }
}
