<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comparte la clave primaria con `users`: es la relacion 1-0..1 del ERD.
 */
#[Fillable([
    'id', 'document_type_id', 'document_number', 'sex_id', 'birth_date',
    'emergency_contact_name', 'emergency_contact_phone_e164',
])]
class Patient extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }
}
