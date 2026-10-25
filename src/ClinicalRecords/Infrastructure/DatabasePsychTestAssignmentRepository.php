<?php

declare(strict_types=1);

namespace App\ClinicalRecords\Infrastructure;

use App\ClinicalRecords\Domain\Port\ClinicalNoteRepository;
use App\ClinicalRecords\Domain\Port\PsychTestAssignmentRepository;
use App\ClinicalRecords\Domain\PsychTest;
use App\ClinicalRecords\Domain\PsychTestAssignment;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cifra las respuestas igual que el SOAP. No guarda el resultado: se recalcula
 * de las respuestas al leer.
 */
final readonly class DatabasePsychTestAssignmentRepository implements PsychTestAssignmentRepository
{
    /** @param ClinicalNoteRepository $records abre el expediente igual que la primera nota */
    public function __construct(
        private ClinicalCipher $cipher,
        private ClinicalNoteRepository $records,
    ) {}

    public function nextId(): string
    {
        return (string) Str::uuid7();
    }

    public function find(string $id): ?PsychTestAssignment
    {
        $row = DB::table('psych_test_assignments')
            ->join('clinical_records', 'clinical_records.id', '=', 'psych_test_assignments.clinical_record_id')
            ->where('psych_test_assignments.id', $id)
            ->first(['psych_test_assignments.*', 'clinical_records.patient_id']);

        if ($row === null) {
            return null;
        }

        $answers = $this->cipher->decrypt($row->answers);

        return PsychTestAssignment::restore(
            $row->id,
            $row->patient_id,
            PsychTest::from($row->test),
            $this->moment($row->due_at),
            $row->assigned_by,
            $answers === null ? null : json_decode($answers, true, flags: JSON_THROW_ON_ERROR),
            $row->submitted_at !== null ? $this->moment($row->submitted_at) : null,
            $row->expired_at !== null ? $this->moment($row->expired_at) : null,
        );
    }

    public function idsPastDue(DateTimeImmutable $now): array
    {
        return DB::table('psych_test_assignments')
            ->whereNull('submitted_at')
            ->whereNull('expired_at')
            ->where('due_at', '<', $this->format($now))
            ->pluck('id')
            ->all();
    }

    public function save(PsychTestAssignment $assignment): void
    {
        $answers = $assignment->answers();
        $row = [
            'answers' => $answers === null ? null : $this->cipher->encrypt(json_encode($answers, JSON_THROW_ON_ERROR)),
            'submitted_at' => $this->format($assignment->submittedAt()),
            'expired_at' => $this->format($assignment->expiredAt()),
            'updated_at' => now(),
        ];

        if (DB::table('psych_test_assignments')->where('id', $assignment->id)->exists()) {
            DB::table('psych_test_assignments')->where('id', $assignment->id)->update($row);

            return;
        }

        DB::table('psych_test_assignments')->insert([
            'id' => $assignment->id,
            'clinical_record_id' => $this->records->recordIdFor($assignment->patientId),
            'test' => $assignment->test->value,
            'assigned_by' => $assignment->assignedBy,
            'due_at' => $this->format($assignment->dueAt),
            'created_at' => now(),
        ] + $row);
    }

    private function format(?DateTimeImmutable $moment): ?string
    {
        return $moment?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function moment(string $utc): DateTimeImmutable
    {
        return new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    }
}
