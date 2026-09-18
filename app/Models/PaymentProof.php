<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $file_path
 * @property Carbon $deposited_on
 */
class PaymentProof extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<Bank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'origin_bank_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['deposited_on' => 'date', 'uploaded_at' => 'datetime'];
    }
}
