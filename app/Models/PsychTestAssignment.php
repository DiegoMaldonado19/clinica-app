<?php

namespace App\Models;

use App\Casts\ClinicalEncrypted;
use App\ClinicalRecords\Domain\PsychTest;
use App\ClinicalRecords\Domain\TestResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Solo lectura para el panel y el portal; las escrituras pasan por PsychTestService.
 *
 * @property PsychTest $test
 * @property Carbon $due_at
 * @property Carbon|null $submitted_at
 * @property string|null $answers
 * @property ClinicalRecord $record
 */
class PsychTestAssignment extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    /** @return BelongsTo<ClinicalRecord, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(ClinicalRecord::class, 'clinical_record_id');
    }

    /** Calculado al leer: pasada la fecha es VENCIDA aunque psych-tests:expire aun no la marque. */
    public function statusCode(): string
    {
        return match (true) {
            $this->submitted_at !== null => 'RESPONDIDA',
            $this->due_at->isPast() => 'VENCIDA',
            default => 'PENDIENTE',
        };
    }

    /** @return list<int>|null */
    public function answerList(): ?array
    {
        return $this->answers === null ? null : json_decode($this->answers, true, flags: JSON_THROW_ON_ERROR);
    }

    public function result(): ?TestResult
    {
        $answers = $this->answerList();

        return $answers === null ? null : $this->test->score($answers);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'test' => PsychTest::class,
            'answers' => ClinicalEncrypted::class,
            'due_at' => 'datetime',
            'submitted_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }
}
